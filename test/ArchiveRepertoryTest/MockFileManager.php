<?php

namespace ArchiveRepertoryTest;

use ArchiveRepertory\File\FileManager;
use Omeka\File\File;

class MockFileManager extends FileManager
{
    public function getSingleFilenameBypassProtectedMethod($filename, $currentFilename)
    {
        return $this->getSingleFilename($filename, $currentFilename);
    }

    public function getExtension(File $file)
    {
        return pathinfo($file->getSourceName(), PATHINFO_EXTENSION);
    }
}
