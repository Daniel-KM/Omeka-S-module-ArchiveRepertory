<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Omeka\Service\File\TempFileFactoryFactory;
use Psr\Container\ContainerInterface;

class MockTempFileFactoryFactory extends TempFileFactoryFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new MockTempFileFactory($services);
    }
}
