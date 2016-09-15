<?php
namespace ArchiveRepertoryTest;

use OmekaTestHelper\File\MockFileWriter;
use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Omeka\Test\AbstractHttpControllerTestCase;
use Omeka\Entity\Item;
use Omeka\Entity\Value;
use Omeka\Entity\Property;
use Omeka\File\File;
use Omeka\ArchiveRepertory\Module;
use Omeka\Entity\Media;
use Omeka\File\ArchiveManager as ArchiveManager;
use ArchiveRepertory\Media\Ingester\UploadAnywhere;

class ManageFilesTest extends OmekaControllerTestCase
{
    protected $_pathsByType = [
        'original' => 'original',
        'fullsize' => 'large',
        'thumbnail' => 'medium',
        'square_thumbnail' => 'square',
    ];

    protected $fileStorageId;
    protected $fileExtension;
    protected $_fileUrl;
    protected $_storagePath;
    protected $item;

    public function setUp()
    {
        parent::setUp();

        $this->overrideConfig();

        $this->loginAsAdmin();

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $config = $services->get('Config');

        $this->item = $api->create('items', [])->getContent();

        $this->fileStorageId = __DIR__ . '/_files/image_test';
        $this->fileExtension = 'png';
        $this->_fileUrl = $this->fileStorageId . '.' . $this->fileExtension;
        $this->_storagePath = $config['local_dir'];
    }

    protected function overrideConfig()
    {
        $services = $this->getServiceLocator();

        $services->setAllowOverride(true);
        $services->setFactory('ArchiveRepertory\FileWriter', 'ArchiveRepertoryTest\MockFileWriterFactory');
        $services->setFactory('Omeka\File\Manager', 'ArchiveRepertoryTest\MockFileManagerFactory');
        $services->setAllowOverride(false);

        $mediaIngesterManager = $services->get('Omeka\MediaIngesterManager');
        $fileManager = $services->get('Omeka\File\Manager');
        $fileWriter = $services->get('ArchiveRepertory\FileWriter');

        $mediaIngesterManager->setAllowOverride(true);
        $mockUpload = new MockUpload($fileManager);
        $mockUpload->setFileWriter($fileWriter);
        $mediaIngesterManager->setService('upload', $mockUpload);
        $mediaIngesterManager->setAllowOverride(false);
    }

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->id());
    }


    /** @test **/
    public function testWithOptionKeepOriginalNameInsertFile()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('archive_repertory_file_keep_original_name', '1');

        $file = new File($this->_fileUrl);
        $file->setSourceName('originalname.png');

        $this->assertEquals('originalname.png', $this->getFileManager()->getStorageName($file));
    }

    /** @test */
    public function testWithOptionNoKeepOriginalFileName()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('archive_repertory_file_keep_original_name', '0');
        $file = new File($this->_fileUrl);
        $this->assertNotEquals('originalname.png', $this->getFileManager()->getStorageName($file));
    }

    /** @test */
    public function testStorageBasePathWithItemId() {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $settings->set('archive_repertory_file_keep_original_name', '1');
        $settings->set('archive_repertory_item_folder', 'id');

        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');
        $storageFilepath = $this->item->id()
            . DIRECTORY_SEPARATOR
            . pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager=$this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));
    }

    /** @test */
    public function testStorageBasePathWithItemNone()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('archive_repertory_item_folder', '');

        $file = new File($this->_fileUrl);
        $file->setSourceName('image_test.png');

        $storageFilepath = DIRECTORY_SEPARATOR . pathinfo($this->_fileUrl, PATHINFO_BASENAME);
        $fileManager = $this->getFileManager();

        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));

    }

    protected function createMediaItem($title, $upload, $file_index = 0)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

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
        ], $upload);

        if ($response->isError()) {
            error_log(var_export($response->getErrors(), true));
        }

        return $response->getContent();
    }

    /** @test */
    public function testStorageBasePathWithIdDirectory()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('archive_repertory_item_folder', 'id');
        $settings->set('archive_repertory_file_keep_original_name', '1');

        $upload = new \Zend\Stdlib\Parameters([
            'file' => [
                [
                    'name' => 'image_uploaded.png',
                    'type' => 'image/png',
                    'tmp_name' => $this->_fileUrl,
                    'content' => file_get_contents($this->_fileUrl),
                    'error' => 0,
                    'size' => 1999,
                ],
            ]
        ]);
        $this->getRequest()->setFiles($upload);

        $this->postDispatch('/admin/item/add', [
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
                    '@value' => 'My_title?',
                ],
            ],
            'o:media' => [
                [
                    'o:ingester' => 'upload',
                    'file_index' => 0,
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
        $this->assertResponseStatusCode(302);

        $location = $this->getResponse()->getHeaders()->get('Location');
        $path = $location->uri()->getPath();
        $itemId = substr(strrchr($path, "/"), 1);

        $file = new File($this->_fileUrl);
        $file->setSourceName('image_uploaded.png');
        $storageFilepath = $itemId . DIRECTORY_SEPARATOR . 'image_uploaded.png';
        $fileManager = $this->getServiceLocator()->get('Omeka\File\Manager');
        $this->assertEquals($storageFilepath, $fileManager->getStoragePath('',$fileManager->getStorageName($file)));
    }

    /**
     * @test
     */
    public function testInsertDuplicateFile()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $fileManager = $services->get('Omeka\File\Manager');

        $settings->set('archive_repertory_file_keep_original_name', '1');
        $settings->set('archive_repertory_item_folder', '1');

        $this->getFileWriter()->addFile($this->_storagePath.'/original/photo.png');
        $this->getFileWriter()->addFile($this->_storagePath.'/original/photo.1.png');
        $this->assertEquals('./photo.2.png', $fileManager->checkExistingFile('photo.png'));
    }

    protected function getFileWriter()
    {
        return $this->getServiceLocator()->get('ArchiveRepertory\FileWriter');
    }

    protected function getFileManager()
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $fileManager = $services->get('Omeka\File\Manager');

        $media = new Media;
        $media->setStorageId($this->fileStorageId);
        $media->setExtension($this->fileExtension);

        $item = $entityManager->find('Omeka\Entity\Item', $this->item->id());
        $media->setItem($item);

        $fileManager->setMedia($media);

        return $fileManager;
    }
}
