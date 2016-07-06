<?php

namespace OmekaTest\Controller;
use Omeka\File\File;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use OmekaTestHelper\File\MockFileWriter;
use OmekaTestHelper\File\Store\LocalStore;
use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Omeka\Test\AbstractHttpControllerTestCase;

class ArchiveRepertoryAdminControllerTest extends OmekaControllerTestCase
{
    protected $site_test = true;
    protected $traceError = true;
    protected $item;
    public function setUp() {
        $this->loginAsAdmin();
        $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
        $module = $manager->getModule('ArchiveRepertory');


        $manager->install($module);
        $this->setDefaultSettings();
        $this->mockFileManager= MockFileManager::class;
        $this->filewriter = new MockFileWriter();
        \ArchiveRepertory\File\OmekaRenameUpload::setFileWriter($this->filewriter);
        \ArchiveRepertory\Module::setFileWriter($this->filewriter);
        \ArchiveRepertory\Service\FileArchiveManagerFactory::setFileManager($this->mockFileManager);
        \ArchiveRepertory\Media\Ingester\UploadAnywhere::setFileInput(new MockFileInput());
        \OmekaTestHelper\File\Store\LocalStore::setFileWriter($this->filewriter);

        parent::setUp();
        $this->loginAsAdmin();
    }

    public function tearDown() {
        $this->loginAsAdmin();
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

    /** @test */
    public function duplicateNameShouldMoveFileWithAnotherName() {
        $this->module= $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('ArchiveRepertory');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder',1);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','1');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_prefix','');


        $itemr=$this->getApplicationServiceLocator()->get('Omeka\ApiManager')->read('items', $this->item->getId());

        $this->_fileUrl = dirname(dirname(__FILE__)).'/_files/image_test.png';
        $this->_fileUrl2 = dirname(dirname(__FILE__)).'/_files/image_test.save.png';
        $fileData= file_get_contents($this->_fileUrl);
        $files = new \Zend\Stdlib\Parameters(['file' => [0 =>['size' => 1000000,
                                                              'name' => 'photo.png',
                                                              'type' => 'image/png',
                                                              'tmp_name' => $this->_fileUrl,
                                                              'size'=>1,
                                                              'error' => 0,
                                                              'content' => file_get_contents($this->_fileUrl)],
                                                         1 =>['size' => 100000,
                                                              'name' => 'photo2.png',
                                                              'type' => 'image/png',
                                                              'tmp_name' => $this->_fileUrl2,
                                                              'size'=>1,
                                                              'error' => 0,
                                                              'content' => file_get_contents($this->_fileUrl2)]
]]);
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

                  ],
                  'o:media' => [
                                0 =>[
                                 'o:ingester' => 'upload',
                                 'file_index' => 0,
                                 'o:is_public' => 1,
                                 'dcterms:title' => [
                                                          [
                                                           'type' => 'literal',
                                                           'property_id' => 1,
                                                           '@value' => 'media1',
                                                          ],

                                 ]],
                                1 =>[
                                 'o:ingester' => 'upload',
                                 'file_index' => 1,
                                 'o:is_public' => 1,
                                 'dcterms:title' => [
                                                          [
                                                           'type' => 'literal',
                                                           'property_id' => 1,
                                                           '@value' => 'media2',
                                                          ],
                                 ],

                                ],
                                2 =>[
                                 'o:ingester' => 'upload',
                                 'file_index' => 1,
                                 'o:is_public' => 1,
                                 'dcterms:title' => [
                                                          [
                                                           'type' => 'literal',
                                                           'property_id' => 1,
                                                           '@value' => 'media2',
                                                          ],
                                 ],

                                ],

                  ],
                  'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),

                                                                          ]);

        foreach ($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->find('Omeka\Entity\Item',$this->item->getId())->getMedia() as $media) {
            $this->assertEquals('My_modified_title/photo.png', $media->getFileName());
        }
    }


}


class MockFileManager extends \ArchiveRepertory\File\ArchiveManager {
    public function storeThumbnails(File $file) {
//        $file->setStorageBaseName(str_replace('.'.$this->getExtension($file),'',$this->getStorageName($file)));
        return true;
    }


}
class MockFileInput extends \Zend\InputFilter\FileInput {
    public function isValid($context=null) {
        return true;
    }
}
