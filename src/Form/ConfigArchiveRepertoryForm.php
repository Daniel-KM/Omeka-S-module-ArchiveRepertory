<?php
namespace ArchiveRepertory\Form;

use Omeka\Form\AbstractForm;
use Omeka\Form\Element\ResourceSelect;
use Omeka\Form\Element\Ckeditor;
use Zend\Form\Element;
class ConfigArchiveRepertoryForm extends AbstractForm {
    protected $local_storage='';
    protected $allow_unicode=false;

    public function setLocalStorage($local_storage) {
        $this->local_storage=$local_storage;
    }

    public function setAllowUnicode($allow_unicode) {
        $this->allow_unicode=$allow_unicode;
    }

    public function buildForm() {
        $this->setAttribute('id', 'config-form');
        $name=$this->translate('How do you want to name your item sets folder, if any?');
        $this->add($this->addResourceSelect('archive_repertory_collection_folder',$name,$this->getRepertoryCollectionFolderInfo()));

        $this->add([
                    'name' => 'archive_repertory_collection_prefix',

                    'type' => 'Text',
                    'options' => [
                                  'label' => $this->translate('Prefix for Item sets.'),
                                  'info' => $this->translate('Choose a prefix, for example "collection:", "record:" or "doc:", to select the appropriate metadata when they are multiple.').' '. $this->translate('Let empty to use simply the first one.')
                    ],
                    'attributes' => [
                                     'id' => 'title',
                    ],
                    ]);



        $this->add($this->getRadioForConvertion('archive_repertory_collection_convert',$this->translate('Convert item sets names')));

        $name=$this->translate('How do you want to name your item folder, if any?');

        $this->add([
                    'name' => 'archive_repertory_item_prefix',
                    'type' => 'Text',
                    'options' => [
                                  'label' => $this->translate('Prefix for Item.'),
                                  'info' =>  'Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.'.' ' . $this->translate('Let empty to use simply the first one.')
                    ],
                    'attributes' => [
                                     'id' => 'archive_repertory_item_prefix',
                    ],
                    ]);

        $this->add($this->getRadioForConvertion('archive_repertory_item_convert',$this->translate('Convert item names')));
        $this->add($this->addResourceSelect('archive_repertory_item_folder',$name,$this->getInfoForItemFolder()));
        $checkbox = new Element\CheckBox('archive_repertory_file_keep_original_name');
        $checkbox->setLabel($this->translate('Keep original filenames'));
        $this->add($checkbox);

        $this->add($this->getRadioForConvertion('archive_repertory_file_convert', $this->translate('Convert file names')));
        $this->add([
                    'name' => 'archive_repertory_file_base_original_name',
                    'type' =>'CheckBox',
                    'options' => [
                                  'label' =>$this->translate('Keep only base of original filenames'),
                                  'info' => $this->translate('If checked, Omeka will keep original filenames of uploaded files and will not hash it.')
                    ]
                    ]);


        $this->add([
                    'name' => 'archive_repertory_derivative_folders',
                    'type' => 'Text',
                    'options' => [
                                  'label' => $this->translate('Other derivation folders'),
                                  'info' =>$this->getDerivativeFolderInfo()
                    ],
                    'attributes' => [
                                     'id' => 'archive_repertory_derivative_folders',
                    ],
                    ]);



        $this->add([
                    'name' => 'archive_repertory_download_max_free_download',
                    'type' => 'Text',
                    'options'=> [
                                 'label'=> $this->translate('Maximum downloads by user'),
                                 'info'=> $this->translate('Above this size, a captcha will be added to avoid too many downloads from a user.').
                                 ' ' . $this->translate('Set a very high size to allow all files to be downloaded.').
                                 ' ' . $this->translate('Note that the ".htaccess" and eventually "routes.ini" files should be updated too.')
                    ],
                    'attributes' => [
                                     'id' => 'archive_repertory_download_max_free_download',

                    ],
                    ]);

        $this->add([
                    'name' => 'archive_repertory_legal_text',
                    'type' => 'TextArea',
                    'options' => [
                                  'label' => $this->translate('Legal agreement'),
                                  'info'=> $this->translate('This text will be shown beside the legal checkbox to download a file.').
                                  ' ' . $this->translate("Let empty if you don't want to use a legal agreement.")

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
        $radio->setOptions(['info'=> $this->translate('By default, the process uses the default internal functions of Omeka to process files.').
                            $this->translate('If needed, the standard functions of PHP can be used.')]);

        $radio->setValueOptions([
                                 'internal' => $this->translate('Omeka internal'),
                                 'direct'=> $this->translate('Php directly')
                                 ]);
        return $radio;
    }


    protected function getRadioForConvertion($name,$label) {
        $info = $this->translate('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).').$this->translate('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
        $radio = new Element\Radio($name);
        $radio->setLabel($label);
        $radio->setOptions(['info' => $info]);
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

    protected function getRepertoryCollectionFolderInfo() {
        $info = $this->translate('If you choose to add a folder, Omeka will add subfolders for each collection in "files" folders, for example "files/original/collection_identifier/".');
        $info .= ' ' . $this->translate('New files will be stored inside them. Old files will be moved when collection will be updated.') . '<br />';
        $info .= $this->translate("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
        $info .= ' ' . $this->translate('So recommended ids are a specific metadata, "Dublin Core:Identifier", "Internal collection id" and eventually "Dublin Core:Title".') . '<br />';
        $info .= $this->translate('If this identifier does not exists, the Omeka internal collection id will be used.');
        return $info;
    }


    protected function getInfoForItemFolder () {
       $info=$this->translate('If you choose to add a folder, Omeka will add subfolders for each item in "files" folders, for example "files/original/unique_identifier/');
        $info .= $this->translate('New files will be stored inside them. Old files will be moved when item will be updated.'). '<br />';;

        $info .= $this->translate("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
        $info .= ' ' . $this->translate('So recommended ids are a specifc metadata, "Dublin Core Identifier", "Internal item id" and eventually "Dublin Core Title".');
        $info .= $this->translate('If this identifier does not exists, the Omeka internal item id will be used.');
        return $info;
   }


    protected function getDerivativeFolderInfo()  {
        $info = $this->translate('By default, Omeka support three derivative folders: "fullsize", "thumbnails" and "square_thumbnails".');
        $info .= ' ' . $this->translate('You can add other ones if needed (comma-separated values, like "special_thumbnails, circles").');
        $info .= ' ' . $this->translate('Folder names should be relative to the files dir "%s".', $this->local_storage);
        $info .= ' ' . $this->translate('If a plugin does not use a standard derivative extension (for example ".jpg" for images), you should specified it just after the folder name, separated with a pipe "|", for example "zoom_tiles|_zdata, circles".');
        $info .=' ' . $this->translate('When this option is used, you should not change collection or item identifier and, at the same time, use a feature of the plugin that create derivative files.');
        $info .=' ' . $this->translate('In that case, divide your process and change collection or identifier, save item, then use your plugin.');
        return $info;

    }
}