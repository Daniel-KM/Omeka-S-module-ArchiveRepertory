<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MockFileManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');

        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $ingesters = $config['archiverepertory']['ingesters'];
        $thumbnailTypes = $config['thumbnails']['types'];

        return new MockFileManager(
            $services->get('ArchiveRepertory\FileWriter'),
            $services->get('ControllerPluginManager')->get('messenger'),
            $services->get('Omeka\Settings'),
            $services->get('MvcTranslator'),
            $basePath,
            $ingesters,
            $thumbnailTypes
        );
    }
}
