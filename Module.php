<?php declare(strict_types=1);

/*
 * Archive Repertory
 *
 * Keeps original names of files and put them in a hierarchical structure.
 *
 * Copyright Daniel Berthereau 2012-2024
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

// Common may be installed but not registered in autoloader, in particular
// during upgrade. So dynamically register all classes of the module.
if (!defined('COMMON_PSR4_FALLBACK')) {
    foreach ([
        OMEKA_PATH . '/modules/Common/src',
        OMEKA_PATH . '/composer-addons/modules/Common/src',
        dirname(__DIR__) . '/Common/src',
    ] as $commonSrc) {
        if (file_exists($commonSrc . '/TraitModule.php')) {
            define('COMMON_PSR4_FALLBACK', $commonSrc);
            spl_autoload_register(static function ($class): void {
                if (str_starts_with($class, 'Common\\')) {
                    $file = COMMON_PSR4_FALLBACK . '/' . strtr(substr($class, 7), '\\', '/') . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
            break;
        }
    }
}

use ArchiveRepertory\Form\ConfigForm;
use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Entity\Media;
use Omeka\Module\AbstractModule;

/**
 * Archive Repertory.
 *
 * @copyright Daniel Berthereau, 2012-2026
 * @copyright BibLibre, 2016
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.91')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.91'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'afterSaveItem'],
            100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'afterSaveItem'],
            100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.post',
            [$this, 'afterDeleteItem'],
            100
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.create.post',
            [$this, 'afterUploadMedia'],
            100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.update.post',
            [$this, 'afterUploadMedia'],
            100
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $data = [];
        $defaultSettings = $config['archiverepertory']['config'];
        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }

        $form->init();
        $form->setData($data);
        $html = $renderer->render('archive-repertory/module/config', [
            'form' => $form,
        ]);
        return $html;
    }

    /**
     * Manage folders for attached files of items.
     */
    public function afterSaveItem(Event $event): void
    {
        // Do nothing during dry run, for example validation of a contribution
        // by module Contribute or tests. The resource should not be persisted,
        // so files should not be moved and the entity manager should not be
        // flushed.
        if ($event->getParam('request')?->getOption('validateOnly')) {
            return;
        }

        $item = $event->getParam('response')->getContent();
        foreach ($item->getMedia() as $media) {
            $this->afterSaveMedia($media);
        }
    }

    /**
     * Manage folders for media.
     */
    public function afterUploadMedia(Event $event): void
    {
        if ($event->getParam('request')?->getOption('validateOnly')) {
            return;
        }
        $media = $event->getParam('response')->getContent();
        $this->afterSaveMedia($media);
    }

    /**
     * Set medias at the right place.
     *
     * @param Media $media
     */
    protected function afterSaveMedia(Media $media): void
    {
        /**
         * @var \ArchiveRepertory\File\FileManager $fileManager
         * @var \ArchiveRepertory\File\FileWriter $fileWriter
         * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
         */
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $config = $services->get('Config');
        $fileManager = $services->get('ArchiveRepertory\FileManager');
        $fileWriter = $services->get('ArchiveRepertory\FileWriter');

        $ingesters = $config['archiverepertory']['ingesters'];
        $ingester = $media->getIngester();
        if (!isset($ingesters[$ingester])) {
            return;
        }

        // Check if the file should be moved (so change its storage id).
        $currentStorageId = $media->getStorageId();
        $newStorageId = $fileManager->getStorageId($media);
        if ($currentStorageId == $newStorageId) {
            return;
        }

        $extension = $media->getExtension();
        $newFilename = $extension ? $newStorageId . '.' . $extension : $newStorageId;

        // The original file may be absent from the local storage for a
        // legitimate reason (remote store, media without a local original,
        // storage still in progress): warn and log, but do not move it.
        $path = $fileManager->getFullArchivePath('original');
        $filepath = $fileManager->concatWithSeparator($path, $media->getFilename());
        if (!$fileWriter->fileExists($filepath)) {
            $message = new PsrMessage(
                'The original file is not in the local storage, so it is not moved: {filepath}.', // @translate
                ['filepath' => $filepath]
            );
            $services->get('ControllerPluginManager')->get('messenger')->addWarning($message);
            $services->get('Omeka\Logger')->warn($message);
            return;
        }

        $result = $fileManager->moveFilesInArchiveFolders(
            $media->getFilename(),
            $newFilename
        );

        if (!$result) {
            $message = new PsrMessage(
                'Cannot move files inside archive directory.' // @translate
            );
            $services->get('ControllerPluginManager')->get('messenger')->addError($message);
            $services->get('Omeka\Logger')->err($message);
            return;
        }

        // Update file in Omeka database immediately for each file.
        // Because this is not a file hook, the file is not automatically saved,
        // so persist and flush are required now.
        $media->setStorageId($newStorageId);
        $entityManager->persist($media);
        $entityManager->flush();
    }

    /**
     * Remove folders for attached files of items.
     *
     * The default files are removed with the standard process.
     */
    public function afterDeleteItem(Event $event): void
    {
        /** @var \ArchiveRepertory\File\FileManager $fileManager */
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $fileManager = $services->get('ArchiveRepertory\FileManager');

        $item = $event->getParam('response')->getContent();
        $ingesters = $config['archiverepertory']['ingesters'];

        // Check if a folder was added without checking settings, because they
        // could change.
        foreach ($item->getMedia() as $media) {
            $ingester = $media->getIngester();
            if (!isset($ingesters[$ingester])) {
                continue;
            }

            // Check if there is a directory to remove. Note: only the "/" is
            // used during the saving.
            $filename = (string) $media->getFilename();
            if (strpos($filename, '/') === false) {
                continue;
            }

            $storageDir = dirname($filename);
            $fileManager->removeArchiveFolders($storageDir);
            // Whatever the result, continue the other medias.
        }
    }
}
