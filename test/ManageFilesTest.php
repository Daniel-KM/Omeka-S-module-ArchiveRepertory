<?php
namespace OmekaTest;
use Omeka\Test\AbstractHttpControllerTestCase;
use Omeka\Entity\Item;
use Omeka\Entity\Value;
use Omeka\Entity\Property;
use Omeka\File\File;
use Omeka\ArchiveRepertory\Module;
use Omeka\Entity\Media;
use Omeka\File\ArchiveManager as ArchiveManager;
class ArchiveRepertory_ManageFilesTest extends AbstractHttpControllerTestCase
{
    protected $_fileUrl;
    protected $module;
    public function setUp() {

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
    }



    public function getFileManager() {

        $fileData= file_get_contents($this->_fileUrl);
        $fileManager = $this->getApplicationServiceLocator()->get('Omeka\File\Manager');

        $media = new Media;
        xdebug_break();
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


    /**
     * @test
     */
    public function testInsertDuplicateFile()
     {
        $item2 = new Item;
        $files = [0 => ['name'=> 'images',
                               'type'=> 'image/png',
                               'tmp_name'=> '/tmp/namae',
                               'error'=> 0,
                               'size'=>1]];
        $this->persistAndSave($item2);
        $api = $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
        $item_1 = $api->create('items', [ 'dcterms:title'=>
                               [
                                [
                                 'property_id'=>  '1',
                                 'type'=> 'literal',
                                 '@value'=> 'A title']]],$files);

        $response = $api->create('items', [
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
                    '@value' => 'Item 1',
                ],
            ],

            'o:media' => [
                [
                    'o:ingester' => 'url',
                    'ingest_url' => 'http://youtube.fr/',
                    'dcterms:identifier' => [
                        [
                            'type' => 'literal',
                            'property_id' => 10,
                            '@value' => 'media1',
                        ],
                    ],
                ],
            ],
                                           ],$files);
        $item_2 = $api->create('items',[
                                        'o:is_public'=>1,
                                        'add-item-submit'=> '',
                                        'dcterms:title'=>
                                        [
                                         'property_id'=>  '1',
                                         'type'=> 'literal',
                                         '@value'=> 'A title'],
                                        'o:media'=> ['o:is_public'=>'1',
                                                     'file_index'=> 0 ,
                                                     'o:ingester' => 'upload']
                                        ], $files);

        $fileManager = $this->getApplicationServiceLocator()->get('Omeka\File\Manager');
        $entityManager = $this->getApplicationServiceLocator()->get('Omeka\EntityManager');


        $this->createValue(2,'My title ?',$this->item);
        $this->createValue(2,'My title ?',$item2);
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','2');
        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = 'My_title'.DIRECTORY_SEPARATOR.pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        xdebug_break();


        $fileManager->setMedia($response->getContent()->primaryMedia());

        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }

    /**
     * Check change of the identifier of the item.
     */
    protected function _testChangeIdentifier()
    {
        // Set default option for identifier of items.
        $elementSetName = 'Dublin Core';
        $elementName = 'Identifier';
        $element = $this->db->getTable('Element')->findByElementSetNameAndElementName($elementSetName, $elementName);
        set_option('archive_repertory_item_folder', $element->id);

        // Update item.
        update_item(
            $this->item,
            array(),
            array($elementSetName => array(
                $elementName => array(array('text' => 'my_first_item', 'html' => false)),
        )));

        $files = $this->item->getFiles();
        foreach ($files as $key => $file) {
            $this->_checkFile($file);
        }
    }

    /**
     * Check change of the collection of the item.
     */
    protected function _testChangeCollection()
    {
        // Create a new collection.
        $this->collection = insert_collection(array('public' => true));

        // Update item.
        update_item($this->item, array('collection_id' => $this->collection->id));

        $files = $this->item->getFiles();
        foreach ($files as $key => $file) {
            $this->_checkFile($file);
        }
    }

    /**
     * Check simultaneous change of identifier and collection of the item.
     */
    protected function _testChangeIdentifierAndCollection()
    {
        $elementSetName = 'Dublin Core';
        $elementName = 'Identifier';

        // Create a new collection.
        $this->collection = insert_collection(array('public' => true));

        // Need to release item and to reload it.
        $itemId = $this->item->id;
        release_object($this->item);
        $this->item = get_record_by_id('Item', $itemId);

        // Update item.
        update_item(
            $this->item,
            array(
                'collection_id' => $this->collection->id,
                'overwriteElementTexts' => true,
            ),
            array($elementSetName => array(
                $elementName => array(array('text' => 'my_new_item_identifier', 'html' => false)),
        )));

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
            $storageFilepath = $this->_storagePath . DIRECTORY_SEPARATOR . $file->getStoragePath($type);
            $this->assertTrue(file_exists($storageFilepath));
        }
    }
}
