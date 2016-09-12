<?php
namespace ArchiveRepertory\Form\Element;

use Omeka\Form\Element\PropertySelect as OmekaPropertySelect;

class PropertySelect extends OmekaPropertySelect
{
    public function getValueOptions()
    {
        $valueOptions = array_merge([
            'id' => 'Internal item ID',
        ], parent::getValueOptions());

        return $valueOptions;
    }
}
