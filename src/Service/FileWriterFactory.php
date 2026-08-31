<?php declare(strict_types=1);

namespace ArchiveRepertory\Service;

use ArchiveRepertory\File\FileWriter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class FileWriterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new FileWriter;
    }
}
