<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class MockFileWriterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MockFileWriter;
    }
}
