<?php
namespace ArchiveRepertory\Service\Form\Element;

use Interop\Container\ContainerInterface;
use ArchiveRepertory\Form\Element\PropertySelect;
use Zend\ServiceManager\Factory\FactoryInterface;

class PropertySelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new PropertySelect;
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
