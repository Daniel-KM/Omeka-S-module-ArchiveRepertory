<?php

namespace ArchiveRepertoryTest;

use Omeka\Media\Ingester\Upload;

class MockUpload extends Upload
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
