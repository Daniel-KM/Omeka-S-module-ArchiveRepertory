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
            throw new ConfigException('Missing thumbnails configuration');
        }
        $thumbnailTypes = $config['thumbnails']['types'];

        if (!isset($config['local_dir'])) {
            throw new ConfigException('Missing local directory configuration in module.config.php for ArchiveRepertory.');
        }
        if (!is_dir($config['local_dir'])) {
            throw new ConfigException(sprintf('The local directory "%s" is not configured properly in module.config.php, check if the repertory exists.', $config['local_dir']));
        }
        $basePath = $config['local_dir'];

        if (!isset($config['archiverepertory']['ingesters'])) {
            throw new ConfigException('Missing Archive Repertory ingesters configuration');
        }
        $ingesters = $config['archiverepertory']['ingesters'];

        return new FileManager(
            $thumbnailTypes,
            $basePath,
            $ingesters,
            $services);
    }
}
