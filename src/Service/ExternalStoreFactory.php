<?php
namespace ArchiveRepertory\Service;
use Omeka\Service;

use Omeka\File\Store\LocalStore;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Service factory for the Local file store.
 */
class ExternalStoreFactory implements FactoryInterface
{
    /**
     * Create and return the Local file store
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return Local
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $logger = $serviceLocator->get('Omeka\Logger');
        $viewHelpers = $serviceLocator->get('ViewHelperManager');
        $serverUrl = $viewHelpers->get('ServerUrl');
        $basePath = $viewHelpers->get('BasePath');
        $config = $serviceLocator->get('Config');
        $localPath = $config['local_dir'];

        $webPath = $serverUrl($basePath(substr($localPath,strlen(OMEKA_PATH))));
        $fileStore = new LocalStore($localPath, $webPath, $logger);
        return $fileStore;
    }
}
