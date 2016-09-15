<?php

namespace ArchiveRepertoryTest;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MockFileManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');

        $fileManager = $config['file_manager'];
        $tempDir = $config['temp_dir'];

        return new MockFileManager($fileManager, $tempDir, $services);
    }
}
