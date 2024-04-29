<?php declare(strict_types=1);

namespace ArchiveRepertory\Service\Form;

use ArchiveRepertory\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ConfigForm(null, $options ?? []);
        return $form
            ->setSettings($services->get('Omeka\Settings'));
    }
}
