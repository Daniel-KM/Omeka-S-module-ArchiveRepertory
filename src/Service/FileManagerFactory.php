<?php
namespace ArchiveRepertory\Service;

use ArchiveRepertory\File\FileManager;
use Interop\Container\ContainerInterface;
use Omeka\Service\Exception\ConfigException;
use Zend\ServiceManager\Factory\FactoryInterface;

class FileManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');

        if (!isset($config['thumbnails']['types'])) {
            throw new ConfigException('Missing thumbnails configuration'); // @translate
        }

        if (!isset($config['archiverepertory']['ingesters'])) {
            throw new ConfigException('Missing Archive Repertory ingesters configuration'); // @translate
        }

        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $thumbnailTypes = $config['thumbnails']['types'];
        $ingesters = $config['archiverepertory']['ingesters'];

        return new FileManager(
            $thumbnailTypes,
            $basePath,
            $ingesters,
            $services
        );
    }
}
