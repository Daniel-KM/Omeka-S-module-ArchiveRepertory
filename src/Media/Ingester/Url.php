<?php
namespace ArchiveRepertory\Media\Ingester;

use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\Manager as FileManager;
use Omeka\Stdlib\ErrorStore;
use Zend\Uri\Http as HttpUri;

class Url extends \Omeka\Media\Ingester\Url
{
    /**
     * {@inheritDoc}
     */
    public function ingest(Media $media, Request $request,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        if (!isset($data['ingest_url'])) {
            $errorStore->addError('error', 'No ingest URL specified');
            return;
        }

        $uri = new HttpUri($data['ingest_url']);
        if (!($uri->isValid() && $uri->isAbsolute())) {
            $errorStore->addError('ingest_url', 'Invalid ingest URL');
            return;
        }

        $fileManager = $this->fileManager;
        $file = $fileManager->getTempFile();
        $file->setSourceName($uri->getPath());
        if (!$fileManager->downloadFile($uri, $file->getTempPath(), $errorStore)) {
            return;
        }
        if (!$fileManager->validateFile($file, $errorStore)) {
            return;
        }

        $file->setStorageId($fileManager->getStorageId($file, $media));

        $media->setStorageId($file->getStorageId());
        $media->setExtension($file->getExtension($fileManager));
        $media->setMediaType($file->getMediaType());
        $media->setSha256($file->getSha256());
        $media->setHasThumbnails($fileManager->storeThumbnails($file));
        if (!array_key_exists('o:source', $data)) {
            $media->setSource($uri);
        }
        if (!isset($data['store_original']) || $data['store_original']) {
            $fileManager->storeOriginal($file);
            $media->setHasOriginal(true);
        }
        if (file_exists($file->getTempPath())) {
            $file->delete();
        }
    }
}
