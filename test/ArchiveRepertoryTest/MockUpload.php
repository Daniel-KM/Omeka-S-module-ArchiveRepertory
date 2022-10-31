<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;
use Omeka\Media\Ingester\Upload;
use Omeka\Stdlib\ErrorStore;

class MockUpload extends Upload
{
    protected $tempFileFactory;

    public function setTempFileFactory(TempFileFactory $tempFileFactory): void
    {
        $this->tempFileFactory = $tempFileFactory;
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore): void
    {
        $data = $request->getContent();
        $fileData = $request->getFileData();

        $index = $data['file_index'];

        $tempFile = $this->tempFileFactory->build();
        $tempFile->setSourceName($fileData['file'][$index]['name']);

        $media->setStorageId($tempFile->getStorageId());
        $media->setExtension($tempFile->getExtension());
        $media->setMediaType($tempFile->getMediaType());
        $media->setSha256($tempFile->getSha256());
        $hasThumbnails = $tempFile->storeThumbnails();
        $media->setHasThumbnails($hasThumbnails);
        $media->setHasOriginal(true);
        if (!array_key_exists('o:source', $data)) {
            $media->setSource($fileData['file'][$index]['name']);
        }
        $tempFile->storeOriginal();
        $tempFile->delete();
    }
}
