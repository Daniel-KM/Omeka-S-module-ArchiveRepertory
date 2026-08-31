<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use ArchiveRepertory\File\FileManager;
use Omeka\Entity\Resource;

class MockFileManager extends FileManager
{
    public function getSingleFilenameBypassProtectedMethod($filename, $currentFilename)
    {
        return $this->getSingleFilename($filename, $currentFilename);
    }

    public function sanitizeNameBypassProtectedMethod($string): string
    {
        return $this->sanitizeName($string);
    }

    public function convertFilenameToBypassProtectedMethod($string, $format): string
    {
        return $this->convertFilenameTo($string, $format);
    }

    public function getResourceFolderNameBypassProtectedMethod(?Resource $resource = null): string
    {
        return $this->getResourceFolderName($resource);
    }
}
