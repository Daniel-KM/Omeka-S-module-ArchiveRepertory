<?php
namespace ArchiveRepertoryTest;

use Omeka\File\Store\LocalStore;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

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

        $localPath = $config['file_manager']['localpath'] . DIRECTORY_SEPARATOR . 'files';
        $webPath = $serverUrl($basePath('files'));
        $fileStore = new LocalStore($localPath, $webPath, $logger);
        return $fileStore;
    }
}
