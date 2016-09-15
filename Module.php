<?php
/*
 * Archive Repertory
 *
 * Keeps original names of files and put them in a hierarchical structure.
 *
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
use Zend\Math\Rand;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use Omeka\Mvc\Controller\Plugin\Messenger;
use ArchiveRepertory\Form\ConfigForm;

class Module extends AbstractModule
{
    /**
     * @var array This plugin's options.
     */
    protected $_options = [
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
        $this->_installOptions($serviceLocator->get('Omeka\Settings'));
    }

    protected function _installOptions($settings) {
        foreach ($this->_options as $key => $value) {
            $settings->set($key, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->_uninstallOptions($serviceLocator->get('Omeka\Settings'));
    }

    protected function _uninstallOptions($settings) {
        foreach ($this->_options as $key => $value) {
            $settings->delete($key);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach('Omeka\Api\Adapter\ItemAdapter',
                                    'api.update.post', [ $this, 'afterSaveItem' ]);
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
     * @param array Options set in the config form.
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $post = $controller->getRequest()->getPost();
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                $settings->set($optionKey, $post[$optionKey]);
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

        $itemId = $event->getParam('request')->getId();
        $item = $entityManager->find('Omeka\Entity\Item', $itemId);

        $archiveFolder = $fileManager->getItemFolderName($item);

        // Check if files are already attached and if they are at the right place.
        $files = $item->getMedia();

        $keep_original_name = $settings->get('archive_repertory_file_keep_original_name');
        foreach ($files as $file) {
            if ($file->getIngester() != 'upload')
                continue;

            $fileManager->setMedia($file);
            $newFilename = $fileManager->concatWithSeparator($archiveFolder, Helpers::basename_special($file->getFilename()));
            if ($keep_original_name === '1') {
                if (!$this->filenameMatchingSourceName($file)) {
                    $source = $file->getSource();
                    $storagePath = $fileManager->getStoragePath('', $source);
                    $newFilename = $fileManager->checkExistingFile($storagePath);
                }
            } else {
                if ($this->filenameMatchingSourceName($file)) {
                    $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
                    $storageId = bin2hex(Rand::getBytes(20)) . '.' . $extension;
                    $newFilename = $fileManager->concatWithSeparator($archiveFolder, $storageId);
                }
            }
            if ($file->getFilename() != $newFilename) {
                // Check if the original file exists, else this is an undetected
                // error during the convert process.
                $path = $fileManager->getFullArchivePath('original');
                $filepath = $fileManager->concatWithSeparator($path, $file->getFilename());
                if (!$fileWriter->fileExists($filepath)) {
                    $msg = $this->translate('This file is not present in the original directory : ' . $filepath);
                    $msg .= ' ' . $this->translate('There was an undetected error before storage, probably during the convert process.');
                    $this->_addError($msg);
                    continue;
                }

                $result = $fileManager->moveFilesInArchiveSubfolders(
                    $file->getFilename(),
                    $newFilename,
                    $fileManager->getDerivativeExtension($file)
                );

                if (!$result) {
                    $msg = $this->translate('Cannot move files inside archive directory.');
                    $this->_addError($msg);
                    continue;
                }

                // Update file in Omeka database immediately for each file.
                $pathParts = pathinfo($newFilename);
                $file->setStorageId($pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename']);
                $file->setExtension($pathParts['extension']);
                // As it's not a file hook, the file is not automatically saved.
                $entityManager->persist($file);
                $entityManager->flush();
            }
        }
    }

    protected function getOption($key)
    {
        $services = $this->getServiceLocator();
        return $services->get('Omeka\Settings')->get($key);
    }

    protected function _addError($msg)
    {
        $messenger = new Messenger;
        $messenger->addError($msg);
    }

    protected function filenameMatchingSourceName($media)
    {
        $source = $media->getSource();
        $sourceBasename = pathinfo($source, PATHINFO_FILENAME);
        $filename = $media->getFilename();
        $filenameBasename = pathinfo($filename, PATHINFO_FILENAME);

        $sourceBasename = preg_replace('/\.\d+$/', '', $sourceBasename);
        $filenameBasename = preg_replace('/\.\d+$/', '', $filenameBasename);

        return $sourceBasename == $filenameBasename;
    }

    protected function translate($string)
    {
        $serviceLocator = $this->getServiceLocator();

        return $serviceLocator->get('MvcTranslator')->translate($string);
    }
}
