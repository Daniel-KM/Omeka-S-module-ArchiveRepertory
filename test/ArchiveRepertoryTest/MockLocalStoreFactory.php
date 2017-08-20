<?php
namespace ArchiveRepertoryTest;

use Interop\Container\ContainerInterface;
use Omeka\File\Store\Local;
use Zend\ServiceManager\Factory\FactoryInterface;

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

        $config = $serviceLocator->get('Config');

        $localPath = $config['local_dir'];
        $webPath = $serverUrl($basePath('files'));
        $fileStore = new Local($localPath, $webPath, $logger);
        return $fileStore;
    }
}
