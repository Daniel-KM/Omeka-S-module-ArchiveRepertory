<?php
namespace ArchiveRepertory\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use ArchiveRepertory\File\Store\LocalStore;

/**
 * Service factory for the Local file store.
 */
class LocalStoreFactory implements FactoryInterface
{
    /**
     * Create and return the Local file store
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return Local
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $logger = $services->get('Omeka\Logger');
        $viewHelpers = $services->get('ViewHelperManager');
        $config = $services->get('Config');
        $fileWriter = $services->get('ArchiveRepertory\FileWriter');
        $serverUrl = $viewHelpers->get('ServerUrl');
        $basePath = $viewHelpers->get('BasePath');
        $localPath = $config['local_dir'];

        $webPath = $serverUrl($basePath(substr($localPath, strlen(OMEKA_PATH))));
        $fileStore = new LocalStore($localPath, $webPath, $logger, $fileWriter);

        return $fileStore;
    }
}
