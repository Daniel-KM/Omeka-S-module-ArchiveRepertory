<?php

namespace ArchiveRepertory\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use ArchiveRepertory\File\FileWriter;

class FileWriterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new FileWriter;
    }
}
