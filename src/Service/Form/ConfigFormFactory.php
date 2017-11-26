<?php
namespace ArchiveRepertory\Service\Form;

use ArchiveRepertory\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');

        $form = new ConfigForm(null, $options);
        $form->setLocalStorage($config['local_dir']);
        $form->setSettings($settings);
        $form->setTranslator($translator);

        return $form;
    }
}
