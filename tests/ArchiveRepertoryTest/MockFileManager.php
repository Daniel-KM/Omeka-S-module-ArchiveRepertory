<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use ArchiveRepertory\File\FileManager;

class MockFileManager extends FileManager
{
    public function getSingleFilenameBypassProtectedMethod($filename, $currentFilename)
    {
        return $this->getSingleFilename($filename, $currentFilename);
    }
}
