<?php

namespace ArchiveRepertoryTest;

use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\File;
use Omeka\Stdlib\ErrorStore;
use Omeka\Media\Ingester\Upload;

class MockUpload extends Upload
{
    public function ingest(Media $media, Request $request,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        $fileData = $request->getFileData();

        $index = $data['file_index'];

        $fileManager = $this->fileManager;

        $fileData = $fileData['file'][$index];

        $file = new File($fileData['tmp_name']);
        $file->setSourceName($fileData['name']);

        $media->setStorageId($file->getStorageId());
        $media->setExtension($file->getExtension($fileManager));
        $media->setMediaType($file->getMediaType());
        $media->setSha256($file->getSha256());
        $media->setHasThumbnails($fileManager->storeThumbnails($file));
        $media->setHasOriginal(true);
        if (!array_key_exists('o:source', $data)) {
            $media->setSource($fileData['name']);
        }
        $fileManager->storeOriginal($file);

        $file->delete();
    }
}
