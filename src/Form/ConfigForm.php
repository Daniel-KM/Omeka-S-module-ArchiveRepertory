<?php
namespace ArchiveRepertory\Form;

use Zend\Form\Element;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;
use ArchiveRepertory\Helpers;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    protected $local_storage = '';
    protected $formElementManager;

    public function setLocalStorage($local_storage)
    {
        $this->local_storage = $local_storage;
    }

    public function setFormElementManager($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    public function init()
    {
        $this->setAttribute('id', 'config-form');

        $this->add([
            'name' => 'archive_repertory_item_set_folder',
            'type' => 'ArchiveRepertory\Form\Element\PropertySelect',
            'options' => [
                'label' => $this->translate('Item set folder'),
                'empty_option' => $this->translate('Don’t add folder'),
            ],
            'attributes' => [
                'id' => 'archive_repertory_item_set_folder',
                'value' => $this->getSetting('archive_repertory_item_set_folder'),
            ],
        ]);

        $this->add([
            'name' => 'archive_repertory_item_set_prefix',
            'type' => 'Text',
            'options' => [
                'label' => $this->translate('Prefix for item sets'),
                'info' => $this->translate('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.')
                    . ' ' . $this->translate('Let empty to use simply the first one.'),
            ],
            'attributes' => [
                'id' => 'archive_repertory_item_set_prefix',
                'value' => $this->getSetting('archive_repertory_item_set_prefix'),
            ],
        ]);

        $this->add(
            $this->getRadioForConversion('archive_repertory_item_set_convert',
                $this->translate('Convert item set names'))
        );

        $this->add([
            'name' => 'archive_repertory_item_folder',
            'type' => 'ArchiveRepertory\Form\Element\PropertySelect',
            'options' => [
                'label' => $this->translate('Item folder'),
                'empty_option' => $this->translate('Don’t add folder'),
            ],
            'attributes' => [
                'id' => 'archive_repertory_item_folder',
                'value' => $this->getSetting('archive_repertory_item_folder'),
            ],
        ]);

        $this->add([
            'name' => 'archive_repertory_item_prefix',
            'type' => 'Text',
            'options' => [
                'label' => $this->translate('Prefix for items'),
                'info' => $this->translate('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.')
                    . ' ' . $this->translate('Let empty to use simply the first one.'),
            ],
            'attributes' => [
                'id' => 'archive_repertory_item_prefix',
                'value' => $this->getSetting('archive_repertory_item_prefix'),
            ],
        ]);

        $this->add(
            $this->getRadioForConversion('archive_repertory_item_convert',
                $this->translate('Convert item names'))
        );

        $radios = $this->getRadioForConversion('archive_repertory_media_convert',
            $this->translate('Convert file names'));
        $valueOptions = $radios->getValueOptions();
        $valueOptions['hash'] = $this->translate('Hash filename (default Omeka)');
        $radios->setValueOptions($valueOptions);
        $this->add($radios);
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

        $info = $this->translate('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).')
            . ' ' . $this->translate('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
        $radio = new Element\Radio($name);
        $radio->setLabel($label);
        $radio->setOptions(['info' => $info]);
        $radio->setValue($this->getSetting($name));

        $not_recommended = (isset($allow_unicode['ascii']) ? ' ' . $this->translate('(not recommended because your server is not fully compatible with Unicode)') : '');
        $recommended = (isset($allow_unicode['cli']) || isset($allow_unicode['fs'])) ? ' ' . $this->translate('(recommended because your server is not fully compatible with Unicode)') : '';

        $radio->setValueOptions([
            'keep' => $this->translate('Keep name as it') . $not_recommended,
            'spaces' => $this->translate('Convert spaces to underscores'),
            'first letter' => $this->translate('Convert first letter only'),
            'first and spaces' => $this->translate('Convert first letter and spaces'),
            'full' => $this->translate('Full conversion to Ascii.') . $recommended,
         ]);

        return $radio;
    }

    protected function addResourceSelect($name, $label, $info = '')
    {
        $translator = $this->getTranslator();
    }
}
