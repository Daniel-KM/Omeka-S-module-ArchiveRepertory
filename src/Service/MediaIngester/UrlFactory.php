<?php
namespace ArchiveRepertory\Service\MediaIngester;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use ArchiveRepertory\Media\Ingester\Url;

class UrlFactory implements FactoryInterface
{
    /**
     * Create the Upload media ingester service.
     *
     * @param ServiceLocatorInterface $mediaIngesterServiceLocator
     * @return Upload
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fileManager = $services->get('Omeka\File\Manager');

        $url = new Url($fileManager);

        return $url;
    }
}
