<?php
namespace ArchiveRepertoryTest;

use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\Stdlib\ErrorStore;
use Omeka\Media\Ingester\Upload;
use Omeka\File\TempFileFactory;

class MockUpload extends Upload
{
    protected $tempFileFactory;

    public function setTempFileFactory(TempFileFactory $tempFileFactory)
    {
        $this->tempFileFactory = $tempFileFactory;
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
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
