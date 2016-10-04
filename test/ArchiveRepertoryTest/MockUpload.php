<?php

namespace ArchiveRepertoryTest;

use ArchiveRepertory\Media\Ingester\UploadAnywhere;

class MockUpload extends UploadAnywhere
{
    protected function getFileInput($file)
    {
        $fileInput = parent::getFileInput($file);
        $fileInput->setAutoPrependUploadValidator(false);
        return $fileInput;
    }

    protected function getFileMediaType($file)
    {
        return 'image/png';
    }

    protected function getFileSha256($file)
    {
        return hash('sha256', '');
    }
}
