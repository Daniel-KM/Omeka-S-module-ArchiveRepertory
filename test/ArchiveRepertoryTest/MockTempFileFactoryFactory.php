<?php
namespace ArchiveRepertoryTest;

use Interop\Container\ContainerInterface;
use Omeka\Service\File\TempFileFactoryFactory;

class MockTempFileFactoryFactory extends TempFileFactoryFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MockTempFileFactory($services);
    }
}
