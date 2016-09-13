<?php

namespace OmekaTest\Controller;
use Omeka\File\File;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use OmekaTestHelper\File\MockFileWriter;
use OmekaTestHelper\File\Store\LocalStore;
use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Omeka\Test\AbstractHttpControllerTestCase;
use Omeka\Mvc\Controller\Plugin\Messenger;

class ArchiveRepertoryAdminControllerTest extends OmekaControllerTestCase
{
    protected $site_test = true;
    protected $traceError = true;
    protected $item;
    public function setUp() {
        $this->loginAsAdmin();
        $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
        $module = $manager->getModule('ArchiveRepertory');

        if ($module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            $manager->install($module);
        }
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

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->getId());
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
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $this->postDispatch('/admin/module/configure?id=ArchiveRepertory', [$name => $value]);
        $this->assertEquals($value, $settings->get($name));
    }

    protected function setDefaultSettings() {
        $this->item = new Item;
        $media = new Media;
        $storageId = '/test/_files/image_test';
        $extension = 'png';
        $url = $storageId . '.' . $extension;
        $fileUrl = dirname(dirname(__FILE__)).$url;
        $ingester = $this->getApplicationServiceLocator()
                         ->get('Omeka\MediaIngesterManager')
                         ->get('upload');

        $media->setStorageId($storageId);
        $media->setExtension($extension);
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
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', '1');
        $settings->set('archive_repertory_item_prefix', 'prefix:');


        $itemr = $this->api()->read('items', $this->item->getId());

        $this->_fileUrl = dirname(dirname(__FILE__)) . '/_files/image_test.png';

        $files = new \Zend\Stdlib\Parameters([
            'file' => [
                1 => [
                    'size' => 1000000,
                    'name' => 'photo.png',
                    'type' => 'image/png',
                    'tmp_name' => $this->_fileUrl,
                    'size' => 1,
                    'error' => 0,
                    'content' => file_get_contents($this->_fileUrl)
                ],
            ],
        ]);
        $this->getRequest()->setFiles($files);

        $this->postDispatch('/admin/item/' . $this->item->getId() . '/edit', [
            ['file' => $files],
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

        $entityManager = $serviceLocator->get('Omeka\EntityManager');
        $item = $entityManager->find('Omeka\Entity\Item', $this->item->getId());
        $media = [];
        foreach ($item->getMedia() as $m) {
            $media[] = $m;
        };
        $this->assertCount(1, $media);
        $this->assertEquals('Other_modified_title/photo.png', $media[0]->getFilename());
    }

    /** @test */
    public function duplicateNameShouldMoveFileWithAnotherName() {
        $this->module= $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('ArchiveRepertory');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder',1);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','1');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_prefix','');

        $this->postDipatchFiles('My modified title','photo.png', 'photo.png');
        $result_expected = ['My_modified_title/photo.png', 'My_modified_title/photo.1.png'];
        $result=[];
        foreach ($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->find('Omeka\Entity\Item',$this->item->getId())->getMedia() as $media)
            { $result[]=$media->getFileName();}
        $this->assertEquals($result_expected,$result);
        $this->_testSuccess();

    }

    protected function _testSuccess() {
        $messenger = new Messenger;
        $this->assertEquals('Item successfully updated', $messenger->get()[1][0][0]);

    }

    /** @test */
    public function differentNameShouldMoveFileWithAnotherName() {
        $this->module= $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('ArchiveRepertory');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder',1);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','1');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_prefix','nonexisting');

        $this->postDipatchFiles('My modified title','photo.png', 'another_file.png');
        $result_expected = [$this->item->getId().'/photo.png', $this->item->getId().'/another_file.png'];
        $result=[];
        foreach ($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->find('Omeka\Entity\Item',$this->item->getId())->getMedia() as $media)
            { $result[]=$media->getFileName();}
        $this->assertEquals($result_expected,$result);

    }


    /** @test */
    public function differentFileShouldMoveFileWithAnotherName()
    {
        $em = $this->getEntityManager();

        $this->setSettings('archive_repertory_item_folder', 1);
        $this->setSettings('archive_repertory_file_keep_original_name', '0');
        $this->setSettings('archive_repertory_item_prefix', '');

        $this->postDipatchFiles('Previous title','photo.1.png', 'another_file.png');
        $this->setSettings('archive_repertory_file_keep_original_name', '1');

        $existing_medias = [];
        $item = $em->find('Omeka\Entity\Item', $this->item->getId());
        foreach ($item->getMedia() as $media) {
            $existing_medias[$media->getId()] = [
                'o:id' => $media->getId(),
                'o:is_public' => 1,
            ];
        }

        $this->postDipatchFiles('My title', 'photo2.png', 'another_file3.png',
            10, 20, $existing_medias);

        $result_expected = [
            'My_title/photo.1.png',
            'My_title/another_file.png',
            'My_title/photo2.png',
            'My_title/another_file3.png',
        ];

        $result = [];
        $item = $em->find('Omeka\Entity\Item', $this->item->getId());
        foreach ($item->getMedia() as $media) {
            $result[] = $media->getFileName();
        }

        $this->assertEquals($result_expected, $result);

    }



    /** @test */
    public function differentFileShouldMoveFileWithIds() {
        $this->module= $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('ArchiveRepertory');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder',1);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','1');

        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_prefix','');

        $this->postDipatchFiles('Previous title','photo.1.png', 'another_file.png');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','0');
        $existing_medias=[];
        foreach ($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->find('Omeka\Entity\Item',$this->item->getId())->getMedia() as $media) {
            $existing_medias[$media->getId()]=[ 'o:id' => $media->getId(),
                                               'o:is_public' => 1];
        }

        $this->postDipatchFiles('My title','photo2.png', 'another_file3.png',10, 20,
        $existing_medias);
        $result_expected = ['My_title/photo.1.png', 'My_title/another_file.png','My_title/photo2.png', 'My_title/another_file3.png'];
        $result=[];
        foreach ($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->find('Omeka\Entity\Item',$this->item->getId())->getMedia() as $media) {
            $this->assertEquals(1,preg_match('/^My_title\/.*\.png/',$media->getFileName()));
        }


    }




    public function postDipatchFiles($title,$name_file1, $name_file2,$id1=0, $id2=1,$existing_ids=[])
    {
        $this->_fileUrl = dirname(dirname(__FILE__)).'/_files/image_test.png';
        $this->_fileUrl2 = dirname(dirname(__FILE__)).'/_files/image_test.save.png';
        $files = new \Zend\Stdlib\Parameters(['file' => [$id1 =>['size' => 1000000,
                                                              'name' => $name_file1,
                                                              'type' => 'image/png',
                                                              'tmp_name' => $this->_fileUrl,
                                                              'size'=>1,
                                                              'error' => 0,
                                                              'content' => file_get_contents($this->_fileUrl)],
                                                         $id2 =>['size' => 100000,
                                                              'name' => $name_file2,
                                                              'type' => 'image/png',
                                                              'tmp_name' => $this->_fileUrl2,
                                                              'size'=>1,
                                                              'error' => 0,
                                                              'content' => file_get_contents($this->_fileUrl)]
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
                                       '@value' => $title,
                                      ],

                  ],
                  'o:media' => array_merge($existing_ids ,[

                                $id1 =>[
                                 'o:ingester' => 'upload',
                                 'file_index' => $id1,
                                 'o:is_public' => 1,
                                 'dcterms:title' => [
                                                          [
                                                           'type' => 'literal',
                                                           'property_id' => 1,
                                                           '@value' => 'media1',
                                                          ],

                                 ]],
                                $id2 =>[
                                 'o:ingester' => 'upload',
                                 'file_index' => $id2,
                                 'o:is_public' => 1,
                                 'dcterms:title' => [
                                                          [
                                                           'type' => 'literal',
                                                           'property_id' => 1,
                                                           '@value' => 'media2',
                                                          ],
                                 ],

                                ],



                                                           ]),
                  'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),

                                                                          ]);
    }
}

class MockFileManager extends \ArchiveRepertory\File\ArchiveManager {
    public function storeThumbnails(File $file)
    {
        $extension = $this->getExtension($file);
        $storageId = str_replace(".$extension", '', $this->getStorageName($file));
        $file->setStorageId($storageId);
        return true;
    }


}
class MockFileInput extends \Zend\InputFilter\FileInput {
    public function isValid($context=null) {
        return true;
    }
}
