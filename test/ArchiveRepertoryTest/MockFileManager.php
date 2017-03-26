<?php

namespace ArchiveRepertoryTest;

use Omeka\File\File;
use ArchiveRepertory\File\Manager as FileManager;

class MockFileManager extends FileManager
{
    public function getSingleFilenameBypassProtectedMethod($filename)
    {
        return $this->getSingleFilename($filename);
    }

    public function storeThumbnails(File $file)
    {
        $extension = $this->getExtension($file);
        $storageId = str_replace(".$extension", '', $this->getStorageName($file));
        $file->setStorageId($storageId);
        return true;
    }

    public function getExtension(File $file)
    {
        return pathinfo($file->getSourceName(), PATHINFO_EXTENSION);
    }
}
