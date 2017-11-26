<?php
namespace ArchiveRepertoryTest;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MockFileManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');

        $thumbnailTypes = $config['thumbnails']['types'];
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $ingesters = $config['archiverepertory']['ingesters'];

        return new MockFileManager(
            $thumbnailTypes,
            $basePath,
            $ingesters,
            $services
        );
    }
}
