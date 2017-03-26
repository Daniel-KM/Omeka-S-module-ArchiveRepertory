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

    public function getExtension(File $file)
    {
        return pathinfo($file->getSourceName(), PATHINFO_EXTENSION);
    }
}
