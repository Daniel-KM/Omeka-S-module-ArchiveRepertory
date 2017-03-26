<?php
namespace ArchiveRepertory\Form\Element;

use Omeka\Form\Element\PropertySelect as OmekaPropertySelect;

class PropertySelect extends OmekaPropertySelect
{
    public function getValueOptions()
    {
        $valueOptions = array_merge([
            'id' => 'Internal numeric id of the resource', // @translate
        ], parent::getValueOptions());

        return $valueOptions;
    }
}
