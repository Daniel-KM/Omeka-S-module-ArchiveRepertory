<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MockFileWriterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MockFileWriter;
    }
}
