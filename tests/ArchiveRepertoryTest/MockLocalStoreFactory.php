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
        /** @see \Omeka\Service\File\Store\LocalFactory::__invoke() */
        $logger = $serviceLocator->get('Omeka\Logger');
        $viewHelpers = $serviceLocator->get('ViewHelperManager');
        $serverUrl = $viewHelpers->get('ServerUrl');
        $basePath = $viewHelpers->get('BasePath');

        $baseUri = $serverUrl($basePath('files'));
        $basePath = $serviceLocator->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        return new Local($basePath, $baseUri, $logger);
    }
}
