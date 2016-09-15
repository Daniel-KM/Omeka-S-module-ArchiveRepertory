<?php
namespace ArchiveRepertory\File;
use Zend\Filter\File\RenameUpload;
use Zend\Filter\AbstractFilter;
use Zend\Filter\Exception;
use Zend\Stdlib\ErrorHandler;

class OmekaRenameUpload extends RenameUpload
{
    protected $fileWriter;

    public function setFileWriter($fileWriter)
    {
        $this->fileWriter = $fileWriter;
    }

    protected function moveUploadedFile($sourceFile, $targetFile)
    {
        ErrorHandler::start();
        $result = $this->fileWriter->moveUploadedFile($sourceFile, $targetFile);
        $warningException = ErrorHandler::stop();
        if (!$result || null !== $warningException) {
            throw new Exception\RuntimeException(
                sprintf("File '%s' could not be renamed. An error occurred while processing the file.", $sourceFile),
                0,
                $warningException
            );
        }

        return $result;
    }

    protected function checkFileExists($targetFile)
    {
        if (file_exists($targetFile)) {
            if ($this->getOverwrite()) {
                unlink($targetFile);
            } else {
                throw new Exception\InvalidArgumentException(
                    sprintf("File '%s' could not be renamed. It already exists.", $targetFile)
                );
            }
        }
    }
}
