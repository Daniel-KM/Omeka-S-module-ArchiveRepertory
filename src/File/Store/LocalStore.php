<?php
namespace ArchiveRepertory\File\Store;

use Omeka\File\Exception;
use Zend\Log\Logger;
use Omeka\File\Store\StoreInterface;

/**
 * Local filesystem file store
 */
class LocalStore implements StoreInterface
{
    /**
     * Local base path.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Base URI.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * @var Logger
     */
    protected $logger;

    protected $fileWriter;

    /**
     * @param string $basePath
     * @param string $baseUri
     */
    public function __construct($basePath, $baseUri, Logger $logger, $fileWriter)
    {
        if (!($fileWriter->is_dir($basePath) && $fileWriter->is_writable($basePath))) {
            throw new Exception\RuntimeException(
                sprintf('Base path "%s" is not a writable directory.', $basePath)
            );
        }

        $this->basePath = realpath($basePath);
        $this->baseUri = $baseUri;
        $this->logger = $logger;
        $this->fileWriter = $fileWriter;
    }

    /**
     * {@inheritDoc}
     */
    public function put($source, $storagePath)
    {
        $localPath = $this->getLocalPath($storagePath);
        $this->assurePathDirectories($localPath);
        $status = $this->fileWriter->rename($source, $localPath);
        $this->fileWriter->chmod($localPath, 0644);
        if (!$status) {
            throw new Exception\RuntimeException(
                sprintf('Failed to move "%s" to "%s".', $source, $localPath)
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete($storagePath)
    {
        $localPath = $this->getLocalPath($storagePath);
        if (!file_exists($localPath)) {
            $this->logger->warn(sprintf(
                'Cannot delete file; file does not exist %s', $localPath
            ));
            return;
        }
        $status = unlink($localPath);
        if (!$status) {
            throw new Exception\RuntimeException(
                sprintf('Failed to delete "%s".', $localPath)
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUri($storagePath)
    {
        return $this->baseUri . '/' . $storagePath;
    }

    /**
     * Get an absolute local path from a storage path
     *
     * @param string $storagePath Storage path
     * @return string Local path
     */
    protected function getLocalPath($storagePath)
    {
        if (preg_match('#(?:^|/)\.{2}(?:$|/)#', $storagePath)) {
            throw new Exception\RuntimeException(
                sprintf('Illegal relative component in path "%s"',
                    $storagePath));
        }
        return $this->basePath . DIRECTORY_SEPARATOR . $storagePath;
    }

    /**
     * Check for directory existence and access for a local path
     *
     * @param string $localPath
     */
    protected function assurePathDirectories($localPath)
    {
        $dir = dirname($localPath);
        if (!$this->fileWriter->is_dir($dir)) {
            $this->fileWriter->mkdir($dir, 0755);
        }

        if (!$this->fileWriter->is_writable($dir)) {
            throw new Exception\RuntimeException(
                sprintf('Directory "%s" is not writable.', $dir)
            );
        }
    }
}
