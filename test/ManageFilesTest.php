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
        $this->_fileUrl = dirname(dirname(__FILE__)).'/test/_files/image_test.png';
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


    protected function createValue($id, $title) {
        $value = new Value;
        $value->setProperty($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->getRepository('Omeka\Entity\Property')->find($id));
        $value->setResource($this->item);
        $value->setValue($title);
        $value->setType('literal');
        $this->persistAndSave($value);
    }

    /** @test */
    public function testStorageBasePathWithSpecificField() {
        $this->createValue(1,'My title ?');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','1');
        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = 'My_title'.DIRECTORY_SEPARATOR.pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager=$this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }


    /** @test */
    public function testStorageBasePathWithPrefixSpecificField() {
        $this->createValue(2,'My title ?');
        $this->createValue(2,'term:another title');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_folder','2');
        $this->module->setOption($this->getApplicationServiceLocator(), 'archive_repertory_item_prefix','term:');
        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = 'another_title'.DIRECTORY_SEPARATOR.pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager=$this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }


    /**
     * Check insertion of a second file with a duplicate name.
     *
     * @internal Omeka allows to have two files with the same name.
     */
    protected function _testInsertDuplicateFile()
    {
        $fileUrl = $this->_fileUrl;
        $files = insert_files_for_item($this->item, 'Filesystem', array($fileUrl));

        // Retrieve files from the database to get a fully inserted file, with
        // all updated metadata.
        $files = $this->item->getFiles();
        $this->assertEquals(2, count($files));
        // Get the second file.
        $file = $files[1];

        // Generic checks.
        $this->assertThat($file, $this->isInstanceOf('File'));
        $this->assertTrue($file->exists());
        $this->assertEquals(filesize($fileUrl), $file->size);
        $this->assertEquals(md5_file($fileUrl), $file->authentication);
        $this->assertEquals(pathinfo($fileUrl, PATHINFO_BASENAME), $file->original_filename);

        // Readable filename check.
        $storageFilepath = $this->item->id
            . DIRECTORY_SEPARATOR
            . pathinfo($fileUrl, PATHINFO_FILENAME)
            . '.1.'
            . pathinfo($fileUrl, PATHINFO_EXTENSION);
        $this->assertEquals($storageFilepath, $file->filename);

        // Readable filepath check.
        $this->_checkFile($file);
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
