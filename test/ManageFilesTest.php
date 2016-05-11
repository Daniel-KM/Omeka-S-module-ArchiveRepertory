<?php
namespace OmekaTest;
use Omeka\Test\AbstractHttpControllerTestCase;
use Omeka\Entity\Item;
use Omeka\Entity\Value;
use Omeka\Entity\Property;
use Omeka\File\File;
use Omeka\ArchiveRepertory\Module;
use Omeka\Entity\Media;
use Omeka\File\OmekaRenameUpload;
use Omeka\File\ArchiveManager as ArchiveManager;
class ArchiveRepertory_ManageFilesTest extends AbstractHttpControllerTestCase
{
    protected $_pathsByType = array(
        'original' => 'original',
        'fullsize' => 'large',
        'thumbnail' => 'medium',
        'square_thumbnail' => 'square',
    );


    protected $_fileUrl;
    protected $module;
    protected $_storagePath;
    public function setUp() {
//        $mock=$this->getMock('FileWriter');
        //      $mock->expects($this->any())->method('moveUploadedFile')->will($this->returnValue(true));
        OmekaRenameUpload::setFileWriter(new MockFileWriter());

        $this->connectAdminUser();
        $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
        $module = $manager->getModule('ArchiveRepertory');

        $manager->install($module);

        parent::setUp();

        $this->module= $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('ArchiveRepertory');

        $this->item = new Item;
        $this->persistAndSave($this->item);
        $this->connectAdminUser();
        $this->_fileUrl = dirname(dirname(__FILE__)).'/test/_files/image_test.png';
        $config = $this->getApplicationServiceLocator()->get('Config');
        $this->_storagePath = $config['local_dir'];


    }


    public function tearDown() {
        $this->connectAdminUser();

        $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
        $module = $manager->getModule('ArchiveRepertory');
        $manager->uninstall($module);

    }

    public function getFileManager() {

        $fileData= file_get_contents($this->_fileUrl);
        $fileManager = $this->getApplicationServiceLocator()->get('Omeka\File\Manager');

        $media = new Media;
        $media->setFilename($this->_fileUrl);
        $media->setItem($this->item);
        $fileManager->setMedia($media);
        return $fileManager;
    }

    /** @test **/
    public function testWithOptionKeepOriginalNameInsertFile()
    {

        $file = new File($this->_fileUrl);
        $file->setSourceName('originalname.png');

        $this->assertEquals('originalname.png', $this->getFileManager()->getStorageName($file));
    }

    /** @test */
    public function testWithOptionNoKeepOriginalFileName() {
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','false');
        $file = new File($this->_fileUrl);
//        $file->setSourceName('originalname.png');
        $this->assertNotEquals('originalname.png', $this->getFileManager()->getStorageName($file));

    }



    /** @test */
    public function testStorageBasePathWithItemId() {
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','id');
        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = $this->item->getId()
            . DIRECTORY_SEPARATOR
            . pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager=$this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }



    /** @test */
    public function testStorageBasePathWithItemNone() {

        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','');
        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = DIRECTORY_SEPARATOR.pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager=$this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }


    protected function createValue($id, $title,$item) {
        $value = new Value;
        $value->setProperty($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->getRepository('Omeka\Entity\Property')->find($id));
        $value->setResource($item);
        $value->setValue($title);
        $value->setType('literal');
        $this->persistAndSave($value);
        $this->persistAndSave($item);
    }

    /** @test */
    public function testStorageBasePathWithSpecificField() {
        $this->createValue(1,'My title ?',$this->item);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','1');
        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = 'My_title'.DIRECTORY_SEPARATOR.pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager=$this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }


    /** @test */
    public function testStorageBasePathWithPrefixSpecificField() {
        $this->createValue(2,'My title ?',$this->item);
        $this->createValue(2,'term:another title',$this->item);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','2');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_prefix','term:');
        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = 'another_title'.DIRECTORY_SEPARATOR.pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager=$this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }

    protected function getUpload($name, $url) {
        $upload = new \Zend\Stdlib\Parameters([
    'file' => [[
                 'name' => $name,
                 'type' => 'image/png',
                 'tmp_name' => $url,

                'content' => file_get_contents($url),
                 'error' => 0,
               'size' => 1999]

    ]
                                               ]);
        $this->getRequest()->setFiles($upload);

        return $upload;
    }

    protected function updateItem($id, $title,$upload,$file_index=0) {
        $api = $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
        return $api->update('items', $id, [
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

                            'o:media' => [
                                          [
                                           'o:ingester' => 'upload',
                                           'file_index' => $file_index,
                                           'dcterms:identifier' => [
                                                                    [
                                                                     'type' => 'literal',
                            'property_id' => 10,
                            '@value' => 'media1',
                                                                    ],
                                           ],
                                          ],
                            ]]
                                           ,$upload);


    }


    protected function createMediaItem($title,$upload,$file_index=0) {
        $api = $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
        return $api->create('items', [
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

            'o:media' => [
                [
                    'o:ingester' => 'upload',
                 'file_index' => $file_index,
                    'dcterms:identifier' => [
                        [
                            'type' => 'literal',
                            'property_id' => 10,
                            '@value' => 'media1',
                        ],
                    ],
                ],
            ],
                                           ],$upload);

}
    /**
     * @test
     */
    public function testInsertDuplicateFile()
     {
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','1');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','1');
        $_FILES['file'] = [['size' => 1000000,
                        'name' => 'photo.png',
                        'type' => 'image/png',
                        'tmp_name' => $this->_fileUrl,
                        'error' => 0]];
        $files =  [
                   'file' => [[
                               'name'=> 'photo.png',
                              'type'=> 'image/png',
                               'tmp_name'=> $this->_fileUrl,
                              'error'=> 0,
                              'size'=>1,
                   'content' => file_get_contents($this->_fileUrl)]]];
        $api = $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
        $upload = $this->getUpload('photo.png',$this->_fileUrl);
        $item = $this->createMediaItem('Item 1',$upload);
        $item2 = $this->createMediaItem('Item 1',$upload);
        $this->postDispatch('/admin/item/'.$item->getContent()->id().'/edit', [
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
                    '@value' => 'Itemee 1',
                ],
            ],
                                                                                  'file' =>                 file_get_contents($this->_fileUrl),
                                                                                  'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
                                               'o:media'=> [[
                                                        'o:ingester' => 'upload',
                                                        'o:is_public'=>'1',
                                                        'file_index'=> 1 ,
                                                        ]]

                         ]);



        $files = $item2->getContent()->media();
        foreach ($files as $file) {
            $this->assertEquals('Item_1/photo.1.png',$file->filename());
        }
        $fileManager = $this->getApplicationServiceLocator()->get('Omeka\File\Manager');
        $entityManager = $this->getApplicationServiceLocator()->get('Omeka\EntityManager');


        $this->createValue(2,'My title ?',$this->item);
        $this->createValue(2,'My title ?',$this->getApplicationServiceLocator()->get('Omeka\ApiAdapterManager')->get('items')->findEntity($item->getContent()->id()));

        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = 'My_title'.DIRECTORY_SEPARATOR.pathinfo($this->_fileUrl, PATHINFO_BASENAME);


        $fileManager->setMedia($entityManager->find('Omeka\Entity\Media',($item->getContent()->primaryMedia()->id())));

        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }

    /**
     * @test
     */
    public function testChangeIdentifier()
    {
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_file_keep_original_name','1');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','1');

        $upload = $this->getUpload('photo.png',$this->_fileUrl);
        $item = $this->createMediaItem('Item 1',$upload);
        xdebug_break();
        $this->updateItem($item->getContent()->id(), 'Autre essai', $upload);

//        $item2 = $this->createMediaItem('Item 1',$upload,1);
        xdebug_break();

        $files = $item->getContent()->media();
        foreach ($files as $file) {
            $this->_checkFile($file);
        }
    }


    /**
     * Check simultaneous change of identifier and collection of the item.
     */
    protected function _testChangeIdentifierAndItem()
    {


        $files = $this->item->getFiles();
        foreach ($files as $key => $file) {
            $this->_checkFile($file);
        }
    }

    /**
     * Check if file and derivatives exist really.
     */
    protected function _checkFile($file)
    {
        foreach ($this->_pathsByType as $type => $path) {
            $storageFilepath = $this->_storagePath . DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR . $file->filename();
            if ($type != 'original')
                $storageFilepath=str_replace('.png','.jpg',$storageFilepath);
            echo "\npath=".$storageFilepath;
            $this->assertTrue(file_exists($storageFilepath));
        }
    }

}
    class MockFileWriter {
  public function moveUploadedFile($source,$destination) {
      echo $destination;
        return copy($source, $destination);
  }




}
