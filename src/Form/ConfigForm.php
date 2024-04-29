<?php declare(strict_types=1);

namespace ArchiveRepertory\Form;

use ArchiveRepertory\Helpers;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;
use Omeka\Settings\Settings;

class ConfigForm extends Form
{
    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    public function init(): void
    {
        $this
            ->add([
                'name' => 'archiverepertory_item_set_folder',
                'type' => OmekaElement\PropertySelect::class,
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
            ])

            ->add([
                'name' => 'archiverepertory_item_set_prefix',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Prefix for item sets', // @translate
                    'info' => 'Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple. Let empty to use simply the first one.', // @translate
                ],
            ])

            ->add(
                $this->getRadioForConversion('archiverepertory_item_set_convert', 'Convert item set names') // @translate
            )

            ->add([
                'name' => 'archiverepertory_item_folder',
                'type' => OmekaElement\PropertySelect::class,
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
            ])

            ->add([
                'name' => 'archiverepertory_item_prefix',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Prefix for items',
                    'info' => 'Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple. Let empty to use simply the first one.', // @translate
                ],
            ])

            ->add(
                $this->getRadioForConversion('archiverepertory_item_convert', 'Convert item names') // @translate
            );

        $radioFiles = $this->getRadioForConversion('archiverepertory_media_convert', 'Convert file names'); // @translate
        $valueOptions = $radioFiles->getValueOptions();
        $valueOptions['hash'] = 'Hash filename (default Omeka)'; // @translate
        $radioFiles->setValueOptions($valueOptions);
        $this
            ->add($radioFiles)

            ->add([
                'name' => 'archiverepertory_keep_parenthesis',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Keep parenthesis when sanitizing filename', // @translate
                    'info' => 'This option is not recommended, because it is less secure and not url-compliant.', // @translate
                ],
            ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'archiverepertory_item_set_folder',
                'required' => false,
            ])
            ->add([
                'name' => 'archiverepertory_item_set_prefix',
                'required' => false,
            ])
            ->add([
                'name' => 'archiverepertory_item_set_convert',
                'required' => false,
            ])
            ->add([
                'name' => 'archiverepertory_item_folder',
                'required' => false,
            ])
            ->add([
                'name' => 'archiverepertory_item_prefix',
                'required' => false,
            ])
            ->add([
                'name' => 'archiverepertory_item_convert',
                'required' => false,
            ])
            ->add([
                'name' => 'archiverepertory_media_convert',
                'required' => false,
            ])
            ->add([
                'name' => 'archiverepertory_keep_parenthesis',
                'required' => false,
            ]);
    }

    protected function getRadioForConversion($name, $label)
    {
        $allowUnicode = Helpers::checkUnicodeInstallation();

        $radio = new Element\Radio($name);
        $radio
            ->setLabel($label)
            ->setOptions([
                'info' => 'Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter). In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.', // @translate
            ])
            ->setValue($this->settings->get($name))
            ->setValueOptions([
                'keep' => isset($allowUnicode['ascii'])
                    ? 'Keep name as it (not recommended because your server is not fully compatible with Unicode)' // @translate
                    : 'Keep name as it', // @translate
                'spaces' => 'Convert spaces to underscores', // @translate
                'first letter' => 'Convert first letter only', // @translate
                'first and spaces' => 'Convert first letter and spaces', // @translate
                'full' => (isset($allow_unicode['cli']) || isset($allow_unicode['fs']))
                    ? 'Full conversion to Ascii (recommended because your server is not fully compatible with Unicode)' // @translate
                    : 'Full conversion to Ascii', // @ŧranslate
             ]);
        return $radio;
    }

    public function setSettings(Settings $settings): self
    {
        $this->settings = $settings;
        return $this;
    }
}
