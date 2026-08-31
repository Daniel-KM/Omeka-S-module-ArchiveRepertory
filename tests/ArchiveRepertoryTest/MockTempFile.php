<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Omeka\File\TempFile;

class MockTempFile extends TempFile
{
    /**
     * @var string
     */
    protected $testpath = '/../fixtures/image_test.png';

    /**
     * Store fake thumbnails without invoking a real thumbnailer.
     *
     * The fixture is simply copied for each configured thumbnail type, so tests
     * don't depend on ImageMagick or Gd being installed.
     */
    public function storeThumbnails()
    {
        $source = __DIR__ . $this->testpath;

        $tempPaths = [];
        $typeConfig = $this->thumbnailManager->getTypeConfig();
        foreach (array_keys($typeConfig) as $type) {
            $destinationTempPath = @tempnam(sys_get_temp_dir(), 'omk_ar_');
            copy($source, $destinationTempPath);
            $tempPaths[$type] = $destinationTempPath;
        }

        foreach ($tempPaths as $type => $tempPath) {
            $this->store($type, 'jpg', $tempPath);
            // Delete the temporary file in case the file store hasn't already.
            @unlink($tempPath);
        }

        return true;
    }
}
