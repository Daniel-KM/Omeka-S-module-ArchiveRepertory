<?php

namespace ArchiveRepertory\Service\Form;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use ArchiveRepertory\Form\Config as ConfigForm;

class ConfigFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $formElementManager = $services->get('FormElementManager');
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');

        $configForm = new ConfigForm(null, $options);
        $configForm->setLocalStorage($config['local_dir']);
        $configForm->setFormElementManager($formElementManager);
        $configForm->setSettings($settings);
        $configForm->setTranslator($translator);

        return $configForm;
    }
}
