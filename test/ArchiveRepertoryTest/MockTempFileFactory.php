<?php
namespace ArchiveRepertoryTest;

use Omeka\File\TempFileFactory;

class MockTempFileFactory extends TempFileFactory
{
    public function build()
    {
        return new MockTempFile($this->tempDir, $this->mediaTypeMap, $this->store, $this->thumbnailManager);
    }
}
