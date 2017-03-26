<?php
namespace ArchiveRepertoryTest;

use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Omeka\Entity\Item;
use Omeka\Entity\Value;
use Omeka\File\File;
use Omeka\Entity\Media;

class ManageFilesTest extends OmekaControllerTestCase
{
    protected $fileStorageId;
    protected $fileExtension;
    protected $fileUrl;
    protected $storagePath;
    protected $item;
    protected $media;

    public function setUp()
    {
        parent::setUp();

        $this->overrideConfig();

        $this->loginAsAdmin();

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $config = $services->get('Config');
        $entityManager = $services->get('Omeka\EntityManager');

        $this->item = $api->create('items', [])->getContent();

        $this->fileStorageId = __DIR__
            . DIRECTORY_SEPARATOR . '_files'
            . DIRECTORY_SEPARATOR . 'image_test';
        $this->fileExtension = 'png';
        $this->fileUrl = $this->fileStorageId . '.' . $this->fileExtension;
        $this->storagePath = $config['local_dir'];

        $this->media = $media = new Media;
        $media->setStorageId($this->fileStorageId);
        $media->setExtension($this->fileExtension);

        $item = $entityManager->find('Omeka\Entity\Item', $this->item->id());
        $media->setItem($item);
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

        $mediaIngesterManager->setAllowOverride(true);
        $mockUpload = new MockUpload($fileManager);
        $mediaIngesterManager->setService('upload', $mockUpload);
        $mediaIngesterManager->setAllowOverride(false);
    }

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->id());
    }

    public function testWithOptionKeepOriginalNameInsertFile()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('archive_repertory_item_folder', '');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $this->assertEquals('originalname', $this->getFileManager()->getStorageId($this->media));
    }

    public function testWithOptionNoKeepOriginalFileName()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('archive_repertory_file_keep_original_name', false);
        $this->assertNotEquals('originalname', $this->getFileManager()->getStorageId($this->media));
    }

    public function testStorageBasePathWithItemId()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $settings->set('archive_repertory_file_keep_original_name', true);
        $settings->set('archive_repertory_item_folder', 'id');

        $storageFilepath = $this->item->id()
            . DIRECTORY_SEPARATOR
            . pathinfo($this->fileUrl, PATHINFO_FILENAME);
        $fileManager = $this->getFileManager();
        $this->assertEquals($storageFilepath, $fileManager->getStorageId($this->media));
    }

    public function testStorageBasePathWithItemNone()
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('archive_repertory_item_folder', '');

        $storageFilepath = pathinfo($this->fileUrl, PATHINFO_FILENAME);
        $fileManager = $this->getFileManager();

        $this->assertEquals($storageFilepath, $fileManager->getStorageId($this->media));
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

    public function testStorageBasePathWithIdDirectory()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $entityManager = $services->get('Omeka\EntityManager');

        $settings->set('archive_repertory_item_folder', 'id');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $upload = new \Zend\Stdlib\Parameters([
            'file' => [
                [
                    'name' => 'image_uploaded.png',
                    'type' => 'image/png',
                    'tmp_name' => $this->fileUrl,
                    'content' => file_get_contents($this->fileUrl),
                    'error' => 0,
                    'size' => 1999,
                ],
            ],
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
        $itemId = substr(strrchr($path, '/'), 1);
        $item = $entityManager->find('Omeka\Entity\Item', $itemId);
        $media = new Media;
        $media->setItem($item);

        $storageFilepath = $itemId . DIRECTORY_SEPARATOR . 'image_uploaded';
        $fileManager = $this->getServiceLocator()->get('Omeka\File\Manager');
        $this->assertEquals($storageFilepath, $fileManager->getStorageId($media));
    }

    public function testInsertDuplicateFile()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $fileManager = $services->get('Omeka\File\Manager');

        $settings->set('archive_repertory_file_keep_original_name', true);
        $settings->set('archive_repertory_item_folder', '1');

        $this->getFileWriter()->addFile($this->storagePath.'/original/photo.png');
        $this->getFileWriter()->addFile($this->storagePath.'/original/photo.1.png');
        $this->assertEquals(
            './photo.2.png',
            $fileManager->checkExistingFileBypassProtectedMethod('photo.png'));
    }

    protected function getFileWriter()
    {
        return $this->getServiceLocator()->get('ArchiveRepertory\FileWriter');
    }

    protected function getFileManager()
    {
        $services = $this->getServiceLocator();

        return $services->get('Omeka\File\Manager');
    }
}
