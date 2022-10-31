<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\File\Store\Local;

/**
 * Service factory for the Local file store.
 */
class MockLocalStoreFactory implements FactoryInterface
{
    /**
     * Create and return the Local file store
     *
     * @return Local
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $logger = $serviceLocator->get('Omeka\Logger');
        $viewHelpers = $serviceLocator->get('ViewHelperManager');
        $serverUrl = $viewHelpers->get('ServerUrl');
        $basePath = $viewHelpers->get('BasePath');

        $basePath = $serviceLocator->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $webPath = $serverUrl($basePath('files'));
        $fileStore = new Local($basePath, $webPath, $logger);
        return $fileStore;
    }
}
