<?php
namespace ArchiveRepertory\Form;

use ArchiveRepertory\Helpers;
use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Element\Text;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    protected $local_storage = '';

    public function setLocalStorage($local_storage)
    {
        $this->local_storage = $local_storage;
    }

    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    public function init()
    {
        $this->add([
            'name' => 'archiverepertory_item_set_folder',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Item set folder', // @translate
                'empty_option' => 'Don’t add folder', // @translate
                'prepend_value_options' => [
                    'id' => 'Internal numeric id of the resource', // @translate
                ],
            ],
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a property', // @translate
            ],
        ]);

        $this->add([
            'name' => 'archiverepertory_item_set_prefix',
            'type' => Text::class,
            'options' => [
                'label' => 'Prefix for item sets', // @translate
                'info' => $this->translate('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.') // @translate
                    . ' ' . $this->translate('Let empty to use simply the first one.'), // @translate
            ],
        ]);

        $this->add(
            $this->getRadioForConversion('archiverepertory_item_set_convert',
                $this->translate('Convert item set names')) // @translate
        );

        $this->add([
            'name' => 'archiverepertory_item_folder',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Item folder', // @translate
                'empty_option' => 'Don’t add folder', // @translate
                'prepend_value_options' => [
                    'id' => 'Internal numeric id of the resource', // @translate
                ],
            ],
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a property', // @translate
            ],
        ]);

        $this->add([
            'name' => 'archiverepertory_item_prefix',
            'type' => Text::class,
            'options' => [
                'label' => 'Prefix for items',
                'info' => $this->translate('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.') // @translate
                . ' ' . $this->translate('Let empty to use simply the first one.'), // @translate
            ],
        ]);

        $this->add(
            $this->getRadioForConversion('archiverepertory_item_convert',
                $this->translate('Convert item names')) // @translate
        );

        $radios = $this->getRadioForConversion('archiverepertory_media_convert',
            $this->translate('Convert file names')); // @translate
        $valueOptions = $radios->getValueOptions();
        $valueOptions['hash'] = $this->translate('Hash filename (default Omeka)'); // @translate
        $radios->setValueOptions($valueOptions);
        $this->add($radios);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'archiverepertory_item_set_folder',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'archiverepertory_item_set_prefix',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'archiverepertory_item_set_convert',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'archiverepertory_item_folder',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'archiverepertory_item_prefix',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'archiverepertory_item_convert',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'archiverepertory_media_convert',
            'required' => false,
        ]);
    }

    protected function getSetting($name)
    {
        return $this->settings->get($name);
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }

    protected function getRadioForConversion($name, $label)
    {
        $allow_unicode = Helpers::checkUnicodeInstallation();

        $info = $this->translate('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).') // @translate
            . ' ' . $this->translate('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.'); // @translate
        $radio = new Element\Radio($name);
        $radio->setLabel($label);
        $radio->setOptions(['info' => $info]);
        $radio->setValue($this->getSetting($name));

        $not_recommended = isset($allow_unicode['ascii'])
            ? ' ' . $this->translate('(not recommended because your server is not fully compatible with Unicode)') // @translate
            : '';
        $recommended = (isset($allow_unicode['cli']) || isset($allow_unicode['fs']))
            ? ' ' . $this->translate('(recommended because your server is not fully compatible with Unicode)') // @translate
            : '';

        $radio->setValueOptions([
            'keep' => $this->translate('Keep name as it') . $not_recommended, // @translate
            'spaces' => $this->translate('Convert spaces to underscores'), // @translate
            'first letter' => $this->translate('Convert first letter only'), // @translate
            'first and spaces' => $this->translate('Convert first letter and spaces'), // @translate
            'full' => $this->translate('Full conversion to Ascii.') . $recommended, // @translate
         ]);

        return $radio;
    }
}
