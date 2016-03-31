<?php
namespace ArchiveRepertory\Form;

use Omeka\Form\AbstractForm;
use Omeka\Form\Element\ResourceSelect;
use Omeka\Form\Element\Ckeditor;
use Zend\Form\Element;
class ConfigArchiveRepertoryForm extends AbstractForm {

    public function buildForm() {
        $this->setAttribute('id', 'config-form');
        $name=$this->translate('How do you want to name your item sets folder, if any?');
        $this->add($this->addResourceSelect('archive_repertory_collection_folder',$name));

        $this->add([
                    'name' => 'archive_repertory_collection_prefix',

                    'type' => 'Text',
                    'options' => [
                                  'label' => $this->translate('Prefix for Item sets.'),
                    ],
                    'attributes' => [
                                     'id' => 'title',
                                     'required' => true,
                    ],
                    ]);


        $this->add($this->getRadioForConvertion('archive_repertory_collection_convert',$this->translate('Convert item sets names')));

        $name=$this->translate('How do you want to name your item folder, if any?');

        $this->add([
                    'name' => 'archive_repertory_item_prefix',
                    'type' => 'Text',
                    'options' => [
                                  'label' => $this->translate('Prefix for Item.'),
                    ],
                    'attributes' => [
                                     'id' => 'archive_repertory_item_prefix',
                                     'required' => true,
                    ],
                    ]);

        $this->add($this->getRadioForConvertion('archive_repertory_item_convert',$this->translate('Convert item names')));
        $this->add($this->addResourceSelect('archive_repertory_item_folder',$name));
        $checkbox = new Element\CheckBox('archive_repertory_file_keep_original_name');
        $checkbox->setLabel($this->translate('Keep original filenames'));
        $this->add($checkbox);

        $this->add($this->getRadioForConvertion('archive_repertory_file_convert', $this->translate('Convert file names')));
        $checkbox = new Element\CheckBox('archive_repertory_file_base_original_name');
        $checkbox->setLabel($this->translate('Keep only base of original filenames'));
        $this->add($checkbox);



        $this->add([
                    'name' => 'archive_repertory_derivative_folders',
                    'type' => 'Text',
                    'options' => [
                                  'label' => $this->translate('Other derivation folders'),
                    ],
                    'attributes' => [
                                     'id' => 'archive_repertory_derivative_folders',
                                     'required' => true,
                    ],
                    ]);



        $this->add([
                    'name' => 'archive_repertory_download_max_free_download',
                    'type' => 'Text',

                    'attributes' => [
                                     'id' => 'archive_repertory_download_max_free_download',

                    ],
                    ]);

        $this->add([
                    'name' => 'archive_repertory_legal_text',
                    'type' => 'TextArea',
                    'options' => [
                                  'label' => $this->translate('Legal agreement'),
                    ],
                    'attributes' => [
                                     'id' => 'archive_repertory_legal_text',
                                  'rows'=> 5,
                                  'cols'=> 60,
                                  'class'=> 'media-html'


                    ],
                    ]);

        $this->add($this->getRadioForProcess());

    }


    protected function translate($args) {
        $serviceLocator = $this->getServiceLocator();
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }

    protected function getRadioForProcess() {
        $radio = new Element\Radio('archive_repertory_move_process');
        $radio->setLabel('Used process');
        $radio->setValueOptions([
                                 'internal' => $this->translate('Omeka internal'),
                                 'direct'=> $this->translate('Php directly')
                                 ]);
        return $radio;
    }


    protected function getRadioForConvertion($name,$label) {
        $radio = new Element\Radio($name);
        $radio->setLabel($label);
        $radio->setValueOptions([
                                 'Keep name' => $this->translate('Keep name as it'),
//                                 . (isset($allow_unicode['ascii'])
                                 //.                                  ? ' ' . $this->translate('(not recommended because your server is not fully compatible with Unicode)')
//                                    : ''),
                                 'Spaces' => $this->translate('Convert spaces to underscores'),
                                 'First letter' => $this->translate('Convert first letter only'),
                                 'First and spaces' => $this->translate('Convert first letter and spaces'),
                                 'Full' => $this->translate('Full conversion to Ascii')
//                                 . ((isset($allow_unicode['cli']) || isset($allow_unicode['fs']))
                                 //                                  ? ' (' . $this->translate('recommended because your server is not fully compatible with Unicode') . ')')
                                 ] );

        return $radio;
    }


    protected function addResourceSelect($name,$label,$info='') {

        $serviceLocator = $this->getServiceLocator();
        $translator = $this->getTranslator();
        $classSelect = new ResourceSelect($serviceLocator);
        $classSelect
            ->setName($name)
            ->setAttribute('id', $name)
            ->setLabel($label)
            ->setEmptyOption($translator->translate('Don\'t add folder'))
            ->setOption('info', $info)
            ->setResourceValueOptions(
                                      'resource_classes',
                                      [
                                      ],
                                      function ($resourceClass, $serviceLocator) {
                                          return [
                                                  $resourceClass->vocabulary()->label(),
                                                  $resourceClass->label()
                                          ];
                }
            );
        $options=$classSelect->getValueOptions();
        $options['id']=$translator->translate('Internal item id');
        asort($options);
        $classSelect->setValueOptions($options);
        return $classSelect;
    }
}