<?php
namespace ArchiveRepertoryTest;

use Omeka\File\TempFile;

class MockTempFile extends TempFile
{
    /**
     * @var string
     */
    protected $testpath = '/_files/image_test.png';

    public function storeThumbnails()
    {
        $this->testpath = __DIR__ . $this->testpath;

        $thumbnailer = $this->thumbnailManager->buildThumbnailer();

        $tempPaths = [];

        try {
            $thumbnailer->setSource($this);
            $thumbnailer->setOptions($this->thumbnailManager->getThumbnailerOptions());
            $typeConfig = $this->thumbnailManager->getTypeConfig();
            foreach (array_keys($typeConfig) as $type) {
                $destinationTempPath = tempnam(sys_get_temp_dir(), 'omk_ar_');
                copy($this->testpath, $destinationTempPath);
                $tempPaths[$type] = $destinationTempPath;
            }
        } catch (\Omeka\File\Exception\CannotCreateThumbnailException $e) {
            // Delete temporary files created before exception was thrown.
            foreach ($tempPaths as $tempPath) {
                @unlink($tempPath);
            }
            return false;
        }

        // Finally, store the thumbnails.
        foreach ($tempPaths as $type => $tempPath) {
            $this->store($type, 'jpg', $tempPath);
            // Delete the temporary file in case the file store hasn't already.
            @unlink($tempPath);
        }

        return true;
    }
}
