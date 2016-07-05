<?php

namespace OmekaTest\Controller;

use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Test\AbstractHttpControllerTestCase;
include_once __DIR__ . '/../../src/Media/Ingester/UploadAnywhere.php';
include_once __DIR__ . '/../../src/File/OmekaRenameUpload.php';
class ArchiveRepertoryAdminControllerTest extends AbstractHttpControllerTestCase
{
    protected $site_test = true;
    protected $traceError = true;
    protected $item;
    public function setUp() {
        $this->connectAdminUser();
        $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
        $module = $manager->getModule('ArchiveRepertory');
        $manager->install($module);
        $this->setDefaultSettings();
        \ArchiveRepertory\Media\Ingester\UploadAnywhere::setFileInput(new MockFileInput());
        \ArchiveRepertory\File\OmekaRenameUpload::setFileWriter(new MockFileWriter());
        \Omeka\File\Store\LocalStore::setFileWriter(new MockFileWriter());
        parent::setUp();
        $this->connectAdminUser();
    }

    public function tearDown() {
        $this->connectAdminUser();
        $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
        $module = $manager->getModule('ArchiveRepertory');
        $manager->uninstall($module);
    }


    public function datas() {
        return [
                ['archive_repertory_item_convert', 'false'],
                ['archive_repertory_item_prefix', 'prefix'],
                ['archive_repertory_item_folder', 'foldername'],
                ['archive_repertory_file_keep_original_name' ,true],
                ['archive_repertory_file_convert', 'Full'],
                ['archive_repertory_file_base_original_name' , false],
                ['archive_repertory_derivative_folders' , 'derive'],
                ['archive_repertory_move_process', 'omeka'],
                ['archive_repertory_download_max_free_download' , 19],
                ['archive_repertory_legal_text' , 'I disagree with terms of use.']
        ];
    }

    /**
     * @test
     * @dataProvider datas
     */
    public function postConfigurationShouldBeSaved($name,$value) {
        $this->postDispatch('/admin/module/configure?id=ArchiveRepertory', [$name => $value]);
        $this->assertEquals($value,$this->getApplicationServiceLocator()->get('Omeka\Settings')->get($name));
    }

    protected function setDefaultSettings() {
        $this->item = new Item;
        $media = new Media;
        $url ='/test/_files/image_test.png';
        $fileUrl = dirname(dirname(__FILE__)).$url;
        $ingester = $this->getApplicationServiceLocator()
                         ->get('Omeka\MediaIngesterManager')
                         ->get('upload');

        $media->setFilename($url);
        $media->setSource('test.png');
        $media->setItem($this->item);
        $media->setIngester('upload');
        $media->setRenderer($ingester->getRenderer());
        foreach ($this->datas() as $data) {
            $this->setSettings($data[0],$data[1]);
        }

        $this->persistAndSave($this->item);
        $this->persistAndSave($media);
    }


    /** @test */
    public function postItemShouldMoveFileInAnotherDirectory() {
        $this->module= $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('ArchiveRepertory');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder',1);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','1');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_prefix','prefix:');


        $itemr=$this->getApplicationServiceLocator()->get('Omeka\ApiManager')->read('items', $this->item->getId());

        $this->_fileUrl = dirname(dirname(__FILE__)).'/_files/image_test.png';
        $fileData= file_get_contents($this->_fileUrl);
        $files = new \Zend\Stdlib\Parameters(['file' => [1 =>['size' => 1000000,
                                                              'name' => 'photo.png',
                                                              'type' => 'image/png',
                                                              'tmp_name' => $this->_fileUrl,
                                                              'size'=>1,
                                                              'error' => 0,
                                                              'content' => file_get_contents($this->_fileUrl)]]]);
        $this->getRequest()->setFiles($files);


        $this->postDispatch('/admin/item/'.$this->item->getId().'/edit', [                                     ['file' =>$files],
                  'dcterms:identifier' => [
                                           [
                                            'type' => 'literal',
                                            'property_id' => '10',
                                            '@value' => 'item1',
                                           ],
                  ],
                  'dcterms:title' => [
                                      [
                                       'type' => 'literal',
                                       'property_id' => '1',
                                       '@value' => 'My modified title',
                                      ],
                                      [
                                       'type' => 'literal',
                                       'property_id' => '1',
                                       '@value' => 'prefix:Other modified title',
                                      ],
                  ],
                  'o:media' => [
                                [
                                 'o:ingester' => 'upload',
                                 'file_index' => 1,
                                 'dcterms:identifier' => [
                                                          [
                                                           'type' => 'literal',
                                                           'property_id' => 10,
                                                           '@value' => 'media1',
                                                          ],
                                 ],
                                ],
                  ],
                  'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),

                                                                          ]);

        foreach ($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->find('Omeka\Entity\Item',$this->item->getId())->getMedia() as $media) {
            $this->assertEquals('Other_modified_title/photo.png', $media->getFileName());
        }
    }
}


class MockFileWriter {

    public function is_dir($path) {
        return true;
    }

    public function moveUploadedFile($source,$destination) {
        return copy($source, $destination);
    }
    public function chmod($path, $permission) {
        return true;
    }

    public function rename($path, $destination) {
        return true;
    }

    public function is_writable($path) {
        return true;
    }

    public function mkdir($directory_name, $permissions='0777') {
        echo $directory_name;
        return true;
    }
}


class MockFileInput extends \Zend\InputFilter\FileInput {
    public function isValid($context=null) {
        return true;
    }
}
