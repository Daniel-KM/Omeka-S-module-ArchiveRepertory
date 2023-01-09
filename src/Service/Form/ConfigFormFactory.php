<?php declare(strict_types=1);

namespace ArchiveRepertory\Service\Form;

use ArchiveRepertory\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $form = new ConfigForm(null, $options ?? []);
        return $form
            ->setLocalStorage($basePath)
            ->setSettings($services->get('Omeka\Settings'))
            ->setTranslator($services->get('MvcTranslator'));
    }
}
