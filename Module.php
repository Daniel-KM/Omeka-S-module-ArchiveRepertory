<?php
/*
 * Archive Repertory
 *
 * Keeps original names of files and put them in a hierarchical structure.
 *
 * Copyright Daniel Berthereau 2012-2017
 * Copyright BibLibre, 2016
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace ArchiveRepertory;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\Event;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Uri\Http as HttpUri;
use Zend\View\Renderer\PhpRenderer;
use Omeka\File\File;
use Omeka\Module\AbstractModule;
use Omeka\Mvc\Controller\Plugin\Messenger;
use ArchiveRepertory\Form\ConfigForm;

class Module extends AbstractModule
{
    /**
     * @var array This plugin's settings.
     */
    protected $settings = [
        // Ingesters that modify the storage id and location of files.
        // Other modules can add their own ingesters.
        // Note: the config is merged in the alphabetic order of modules.
        'archive_repertory_ingesters' => [
            // An empty array means that the thumbnail types / paths in config
            // and the default extension ("jpg") will be used.
            // See the module IIIF Server for a full example.
            'upload' => [],
            'url' => [],
        ],

        // Items options.
        'archive_repertory_item_folder' => 'id',
        'archive_repertory_item_prefix' => '',
        'archive_repertory_item_convert' => 'Full',

        // Files options.
        'archive_repertory_file_keep_original_name' => '1',

        // Other derivative folders.
        'archive_repertory_derivative_folders' => '',
    ];

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->_installSettings($serviceLocator->get('Omeka\Settings'));
    }

    protected function _installSettings($settings)
    {
        foreach ($this->settings as $key => $value) {
            $settings->set($key, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->_uninstallSettings($serviceLocator->get('Omeka\Settings'));
    }

    protected function _uninstallSettings($settings)
    {
        foreach ($this->settings as $key => $value) {
            $settings->delete($key);
        }
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        if (version_compare($oldVersion, '3.14.0', '<')) {
            $settings = $serviceLocator->get('Omeka\Settings');
            $settings->set('archive_repertory_ingesters',
                $this->settings['archive_repertory_ingesters']);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.create.post',
            [$this, 'afterSaveItem']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.update.post',
            [$this, 'afterSaveItem']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $forms = $this->getServiceLocator()->get('FormElementManager');
        $form = $forms->get(ConfigForm::class);
        return $renderer->render('archive-repertory/config-form', [
            'form' => $form,
        ]);
    }

    /**
     * Saves plugin configuration page and creates folders if needed.
     *
     * @param array Settings set in the config form.
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $post = $controller->getRequest()->getPost();
        foreach ($this->settings as $key => $value) {
            if (isset($post[$key])) {
                $settings->set($key, $post[$key]);
            }
        }
    }

    /**
     * Manages folders for attached files of items.
     */
    public function afterSaveItem(Event $event)
    {
        $services = $this->getServiceLocator();
        $fileManager = $services->get('Omeka\File\Manager');
        $entityManager = $services->get('Omeka\EntityManager');
        $settings = $services->get('Omeka\Settings');
        $fileWriter = $services->get('ArchiveRepertory\FileWriter');

        $item = $event->getParam('response')->getContent();
        $ingesters = $settings->get('archive_repertory_ingesters');

        // Check if files are already attached and if they are at the right place.
        foreach ($item->getMedia() as $media) {
            $ingester = $media->getIngester();
            if (!isset($ingesters[$ingester])) {
                continue;
            }

            if ($this->fileShouldBeMoved($media)) {
                $file = new File('');
                $file->setSourceName($this->getMediaSourceName($media));
                $storageId = $fileManager->getStorageId($file, $media);
                $extension = $media->getExtension();
                $newFilename = $extension ? $storageId . '.' . $extension : $storageId;

                // Check if the original file exists, else this is an undetected
                // error during the convert process.
                $path = $fileManager->getFullArchivePath('original');
                $filepath = $fileManager->concatWithSeparator($path, $media->getFilename());
                if (!$fileWriter->fileExists($filepath)) {
                    $msg = $this->translate('This file is not present in the original directory : ' . $filepath);
                    $msg .= ' ' . $this->translate('There was an undetected error before storage, probably during the convert process.');
                    $this->_addError($msg);
                    continue;
                }

                $result = $fileManager->moveFilesInArchiveSubfolders(
                    $media->getFilename(),
                    $newFilename,
                    $fileManager->getDerivativeExtension($media)
                );

                if (!$result) {
                    $msg = $this->translate('Cannot move files inside archive directory.');
                    $this->_addError($msg);
                    continue;
                }

                // Update file in Omeka database immediately for each file.
                $media->setStorageId($storageId);

                // As it's not a file hook, the file is not automatically saved.
                $entityManager->persist($media);
                $entityManager->flush();
            }
        }
    }

    protected function _addError($msg)
    {
        $messenger = new Messenger;
        $messenger->addError($msg);
    }

    protected function fileShouldBeMoved($media)
    {
        $services = $this->getServiceLocator();
        $fileManager = $services->get('Omeka\File\Manager');
        $settings = $services->get('Omeka\Settings');

        $keep_original_name = $settings->get('archive_repertory_file_keep_original_name');

        if ($keep_original_name && !$this->filenameMatchingSourceName($media)) {
            return true;
        }

        if (!$keep_original_name && $this->filenameMatchingSourceName($media)) {
            return true;
        }

        $archiveFolder = $fileManager->getItemFolderName($media->getItem());
        $newFilename = $fileManager->concatWithSeparator($archiveFolder, Helpers::basename_special($media->getFilename()));

        return $media->getFilename() != $newFilename;
    }

    protected function filenameMatchingSourceName($media)
    {
        $source = $this->getMediaSourceName($media);
        $sourceBasename = pathinfo($source, PATHINFO_FILENAME);
        $filename = $media->getFilename();
        $filenameBasename = pathinfo($filename, PATHINFO_FILENAME);

        $sourceBasename = preg_replace('/\.\d+$/', '', $sourceBasename);
        $filenameBasename = preg_replace('/\.\d+$/', '', $filenameBasename);

        return $sourceBasename == $filenameBasename;
    }

    protected function getMediaSourceName($media)
    {
        if ($media->getIngester() == 'url') {
            $uri = new HttpUri($media->getSource());
            $sourceName = $uri->getPath();
        } else {
            $sourceName = $media->getSource();
        }

        return $sourceName;
    }

    protected function translate($string)
    {
        $serviceLocator = $this->getServiceLocator();

        return $serviceLocator->get('MvcTranslator')->translate($string);
    }
}
