<?php
namespace ArchiveRepertory\Media\Ingester;
use Omeka\Media\Ingester\Upload;

use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\Stdlib\ErrorStore;
use ArchiveRepertory\File\OmekaRenameUpload;
use Zend\Form\Element\File;
use Zend\InputFilter\FileInput;
use Zend\View\Renderer\PhpRenderer;

class UploadAnywhere extends Upload
{
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
            foreach($fileInput->getMessages() as $message) {
                $errorStore->addError('upload', $message);
            }
            return;
        }

        // Actually process and move the upload
        $fileInput->getValue();
        $fileManager->setMedia($media);
        $file->setSourceName($fileData['name']);
        $hasThumbnails = $fileManager->storeThumbnails($file);

        $fileManager->storeOriginal($file);
        $media->setStorageId($fileManager->getStoragePath('',$fileManager->getStorageName($file)));
        $media->setMediaType($this->getFileMediaType($file));
        $media->setHasThumbnails($hasThumbnails);
        $media->setHasOriginal(true);

        if (!array_key_exists('o:source', $data)) {
            $media->setSource($fileData['name']);
        }
    }

    public function setFileWriter($fileWriter)
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
            'overwrite' => true
        ]);
        $renameUpload->setFileWriter($this->getFileWriter());
        $filterChain = $fileInput->getFilterChain();
        $filterChain->attach($renameUpload);
        return $fileInput;
    }

    /**
     * Just to be easily overriden in tests.
     */
    protected function getFileMediaType($file)
    {
        return $file->getMediaType();
    }

    /**
     * {@inheritDoc}
     */
    public function form(PhpRenderer $view, array $options = [])
    {
        $fileInput = new File('file[__index__]');
        $fileInput->setOptions([
            'label' => $view->translate('Upload File'),
            'info' => $view->uploadLimit(),
        ]);
        $fileInput->setAttributes([
            'id' => 'media-file-input-__index__',
        ]);
        $field = $view->formRow($fileInput);
        return $field . '<input type="hidden" name="o:media[__index__][file_index]" value="__index__">';
    }
}
