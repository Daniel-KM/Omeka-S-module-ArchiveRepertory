<?php
namespace ArchiveRepertory\Service\MediaIngester;

use ArchiveRepertory\Media\Ingester\UploadAnywhere;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class UploadFactory implements FactoryInterface
{
    /**
     * Create the Upload media ingester service.
     *
     * @param ServiceLocatorInterface $mediaIngesterServiceLocator
     * @return Upload
     */
    public function createService(ServiceLocatorInterface $mediaIngesterServiceLocator)
    {
        $serviceLocator = $mediaIngesterServiceLocator->getServiceLocator();
        $fileManager = $serviceLocator->get('Omeka\File\Manager');
        return new UploadAnywhere($fileManager);
    }
}
