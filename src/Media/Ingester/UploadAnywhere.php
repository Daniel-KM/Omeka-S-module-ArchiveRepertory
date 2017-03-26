<?php
namespace ArchiveRepertory\Media\Ingester;

use ArchiveRepertory\File\FileWriter;
use ArchiveRepertory\File\OmekaRenameUpload;
use Omeka\Media\Ingester\Upload;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\Stdlib\ErrorStore;
use Zend\Form\Element\File;
use Zend\InputFilter\FileInput;

class UploadAnywhere extends Upload
{
    /**
     * @var FileWriter
     */
    protected $fileWriter;

    /**
     * {@inheritDoc}
     */
    public function ingest(Media $media, Request $request,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        $fileData = $request->getFileData();
        if (!isset($fileData['file'])) {
            $errorStore->addError('error', 'No files were uploaded');
            return;
        }

        if (!isset($data['file_index'])) {
            $errorStore->addError('error', 'No file index was specified');
            return;
        }

        $index = $data['file_index'];
        if (!isset($fileData['file'][$index])) {
            $errorStore->addError('error', 'No file uploaded for the specified index');
            return;
        }

        $fileManager = $this->fileManager;
        $file = $fileManager->getTempFile();

        $fileInput = $this->getFileInput($file);

        $fileData = $fileData['file'][$index];
        $fileInput->setValue($fileData);
        if (!$fileInput->isValid()) {
            foreach ($fileInput->getMessages() as $message) {
                $errorStore->addError('upload', $message);
            }
            return;
        }
        $fileInput->getValue();
        $file->setSourceName($fileData['name']);
        if (!$fileManager->validateFile($file, $errorStore)) {
            return;
        }

        $file->setStorageId($fileManager->getStorageId($file, $media));

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
        if (file_exists($file->getTempPath())) {
            $file->delete();
        }
    }

    public function setFileWriter(FileWriter $fileWriter)
    {
        $this->fileWriter = $fileWriter;
    }

    public function getFileWriter()
    {
        return $this->fileWriter;
    }

    protected function getFileInput($file)
    {
        $fileInput = new FileInput('file');
        $renameUpload = new OmekaRenameUpload([
            'target' => $file->getTempPath(),
            'overwrite' => true,
        ]);
        $renameUpload->setFileWriter($this->getFileWriter());
        $filterChain = $fileInput->getFilterChain();
        $filterChain->attach($renameUpload);
        return $fileInput;
    }
}
