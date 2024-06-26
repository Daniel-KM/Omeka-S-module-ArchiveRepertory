<?php declare(strict_types=1);

namespace ArchiveRepertory\Service;

use ArchiveRepertory\File\FileManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Service\Exception\ConfigException;

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
        $ingesters = $config['archiverepertory']['ingesters'];
        $thumbnailTypes = $config['thumbnails']['types'];

        return new FileManager(
            $services->get('ArchiveRepertory\FileWriter'),
            $services->get('ControllerPluginManager')->get('messenger'),
            $services->get('Omeka\Settings'),
            $services->get('MvcTranslator'),
            $basePath,
            $ingesters,
            $thumbnailTypes
        );
    }
}
