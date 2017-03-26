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
            'name' => 'archive_repertory_item_folder',
            'type' => 'ArchiveRepertory\Form\Element\PropertySelect',
            'options' => [
                'label' => $this->translate('How do you want to name your item folder, if any?'),
                'info' => $this->getInfoForItemFolder(),
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
                'label' => $this->translate('Prefix for Item.'),
                'info' => $this->translate('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.')
                    . ' ' . $this->translate('Let empty to use simply the first one.'),
            ],
            'attributes' => [
                'id' => 'archive_repertory_item_prefix',
                'value' => $this->getSetting('archive_repertory_item_prefix'),
            ],
        ]);

        $this->add(
            $this->getRadioForConvertion('archive_repertory_item_convert',
            $this->translate('Convert item names'))
        );

        $this->add([
            'name' => 'archive_repertory_file_keep_original_name',
            'type' => 'Checkbox',
            'options' => [
                'label' => $this->translate('Keep original filenames'),
                'info' => $this->translate('If checked, Omeka will keep original filenames of uploaded files and will not hash it.'),
            ],
            'attributes' => [
                'value' => $this->getSetting('archive_repertory_file_keep_original_name'),
            ],
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

    protected function getRadioForConvertion($name, $label)
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
            'keep name' => $this->translate('Keep name as it') . $not_recommended,
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

    protected function getInfoForItemFolder()
    {
        $info = $this->translate('If you choose to add a folder, Omeka will add subfolders for each item in "files" folders, for example "files/original/unique_identifier/".');
        $info .= ' ' . $this->translate('New files will be stored inside them. Old files will be moved when item will be updated.');
        $info .= '<br />';
        $info .= $this->translate("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
        $info .= ' ' . $this->translate('So recommended ids are a specifc metadata, "Dublin Core Identifier", "Internal item id" and eventually "Dublin Core Title".');
        $info .= $this->translate('If this identifier does not exists, the Omeka internal item id will be used.');
        return $info;
    }

    protected function getDerivativeFolderInfo()
    {
        $info = $this->translate('By default, Omeka support three derivative folders: "large", "medium" and "square".');
        $info .= ' ' . $this->translate('You can add other ones if needed (comma-separated values, like "circle, micro").');
        $info .= ' ' . $this->translate('Folder names should be relative to the files dir ') . '"' . $this->local_storage . '"';
        $info .= ' ' . $this->translate('If a plugin does not use a standard derivative extension (for example ".jpg" for images), you should specified it just after the folder name, separated with a pipe "|", for example "tile|_zdata, circle".');
        $info .= ' ' . $this->translate('When this option is used, you should not change collection or item identifier and, at the same time, use a feature of the plugin that create derivative files.');
        $info .= ' ' . $this->translate('In that case, divide your process and change collection or identifier, save item, then use your plugin.');
        return $info;
    }

    /*
     * Checks if all the system (server + php + web environment) allows to
     * manage Unicode filename securely.
     *
     * @internal This function simply checks the true result of functions
     * escapeshellarg() and touch with a non Ascii filename.
     *
     * @return array of issues.
     */
}
