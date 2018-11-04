<?php
namespace ArchiveRepertoryTest;

use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Symfony\Component\Filesystem\Filesystem;

class FileManagerTest extends OmekaControllerTestCase
{
    private $umask;
    protected $filesystem;
    protected $workspace;

    protected $entityManager;
    protected $fileManager;

    /**
     * @var ItemSet
     */
    protected $itemSet;

    /**
     * @var Item
     */
    protected $item;

    /**
     * @var Media
     */
    protected $media;

    /**
     * @var array
     */
    protected $file;

    protected $source;
    protected $tempname;

    public function setUp()
    {
        parent::setUp();

        $this->loginAsAdmin();

        $this->prepareArchiveDir();
        $originalPath = $this->workspace
            . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . 'original';
        mkdir($originalPath, 0777, true);
        mkdir($this->workspace . DIRECTORY_SEPARATOR . 'tmp', 0777, true);

        $this->overrideConfig();

        $services = $this->getServiceLocator();
        // $config = $services->get('Config');
        $entityManager = $services->get('Omeka\EntityManager');

        $this->entityManager = $entityManager;

        $api = $this->api();
        // $settings = $this->settings();

        $source = __DIR__
            . DIRECTORY_SEPARATOR . '_files'
            . DIRECTORY_SEPARATOR . 'image_test.png';
        $this->source = $source;

        $file = [];
        $file['extension'] = pathinfo($source, PATHINFO_EXTENSION);
        $file['media_type'] = 'image/png';
        $file['size'] = filesize($source);
        $file['content'] = file_get_contents($source);
        $file['filename'] = pathinfo($source, PATHINFO_BASENAME);
        $file['filepath'] = $originalPath . DIRECTORY_SEPARATOR . $file['filename'];
        $this->file = $file;

        $itemSet = $api->create('item_sets', [])->getContent();
        $this->itemSet = $entityManager->find('Omeka\Entity\ItemSet', $itemSet->id());
        $item = $api->create('items', ['o:item_set' => [$itemSet->id()]])->getContent();
        $this->item = $entityManager->find('Omeka\Entity\Item', $item->id());

        $media = new Media;
        $media->setItem($this->item);
        $media->setIngester('upload');
        $media->setRenderer('file');
        $media->setSource($file['filename']);
        $media->setMediaType($file['media_type']);
        $media->setExtension($file['extension']);
        $media->setHasOriginal(1);
        $media->setHasThumbnails(1);
        $media->setStorageId($this->hashStorageName($media));
        $this->media = $media;
    }

    /**
     * @see \Symfony\Component\Filesystem\Tests\FilesystemTestCase
     */
    protected function prepareArchiveDir()
    {
        $this->umask = umask(0);
        $this->filesystem = new Filesystem();
        $this->workspace = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . microtime(true) . '.' . mt_rand();
        mkdir($this->workspace, 0777, true);
        $this->workspace = realpath($this->workspace);
    }

    protected function overrideConfig()
    {
        $services = $this->getServiceLocator();

        $services->setAllowOverride(true);

        $config = $services->get('Config');
        $config['temp_dir'] = $this->workspace;
        $config['file_store']['local']['base_path'] = $this->workspace . DIRECTORY_SEPARATOR . 'files';
        $config = $services->setService('Config', $config);

        $services->setFactory('Omeka\File\Store\Local', \ArchiveRepertoryTest\MockLocalStoreFactory::class);
        $services->setFactory('Omeka\File\TempFileFactory', \ArchiveRepertoryTest\MockTempFileFactoryFactory::class);
        $services->setFactory('ArchiveRepertory\FileManager', \ArchiveRepertoryTest\MockFileManagerFactory::class);
        $services->setAllowOverride(false);

        $validator = $services->get('Omeka\File\Validator');
        $uploader = $services->get('Omeka\File\Uploader');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');

        $mediaIngesterManager = $services->get('Omeka\Media\Ingester\Manager');
        $mediaIngesterManager->setAllowOverride(true);
        $mockUpload = new MockUpload($validator, $uploader);
        $mockUpload->setTempFileFactory($tempFileFactory);
        $mediaIngesterManager->setService('upload', $mockUpload);
        $mediaIngesterManager->setAllowOverride(false);

        $fileManager = $services->get('ArchiveRepertory\FileManager');
        $this->fileManager = $fileManager;
    }

    public function tearDown()
    {
        $this->api()->delete('item_sets', $this->itemSet->getId());
        $this->api()->delete('items', $this->item->getId());

        $this->filesystem->remove($this->workspace);
        umask($this->umask);
    }

    public function testKeepOriginalFileName()
    {
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', '');
        $this->settings()->set('archiverepertory_media_convert', 'hash');

        $this->assertNotEquals('image_test', $this->fileManager->getStorageId($this->media));
    }

    public function testDontKeepOriginalName()
    {
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', '');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->assertEquals(
            pathinfo($this->file['filename'], PATHINFO_FILENAME),
            $this->fileManager->getStorageId($this->media));
    }

    public function testAddItemIdWithoutOriginalName()
    {
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'hash');

        $this->assertEquals(
            $this->item->getId() . '/' . $this->media->getStorageId(),
            $this->fileManager->getStorageId($this->media));
    }

    public function testAddItemIdAndOriginalName()
    {
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->assertEquals(
            $this->item->getId() . '/' . pathinfo($this->file['filename'], PATHINFO_FILENAME),
            $this->fileManager->getStorageId($this->media));
    }

    public function testAddItemSetIdAndItemIdAndOriginalName()
    {
        $this->settings()->set('archiverepertory_item_set_folder', 'id');
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->assertEquals(
            $this->itemSet->getId() . '/' . $this->item->getId() . '/' . pathinfo($this->file['filename'], PATHINFO_FILENAME),
            $this->fileManager->getStorageId($this->media));
    }

    public function testStorageBasePathWithIdDirectory()
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $tempname = $this->workspace
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . md5($this->source . microtime(true) . '.' . mt_rand());
        copy($this->source, $tempname);

        $upload = new \Zend\Stdlib\Parameters([
            'file' => [
                1 => [
                    'name' => 'image_uploaded.png',
                    'type' => $this->file['media_type'],
                    'tmp_name' => $tempname,
                    'content' => $this->file['content'],
                    'error' => 0,
                    'size' => $this->file['size'],
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

        $this->assertResponseStatusCode(302);

        $headers = $this->getResponse()->getHeaders();
        $location = $headers->get('Location');
        $this->assertNotEmpty($location);

        $path = $location->uri()->getPath();
        $itemId = substr(strrchr($path, '/'), 1);
        $item = $this->api()->read('items', $itemId)->getContent();
        $medias = $item->media();
        $mediaId = $medias[0]->id();
        $media = $this->entityManager->find('Omeka\Entity\Media', $mediaId);
        $this->assertEquals(
            $itemId . '/' . 'image_uploaded',
            $media->getStorageId());
    }

    public function testInsertDuplicateFile()
    {
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $originalPath = $this->workspace
            . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . 'original';
        touch($originalPath . DIRECTORY_SEPARATOR . 'photo.png');

        $this->assertEquals(
            'photo.1.png',
            $this->fileManager->getSingleFilenameBypassProtectedMethod('photo.png', null));
        touch($originalPath . DIRECTORY_SEPARATOR . 'photo.1.png');

        // $filepath = $originalPath . DIRECTORY_SEPARATOR . 'photo.png';
        $this->assertEquals(
            'photo.2.png',
            $this->fileManager->getSingleFilenameBypassProtectedMethod('photo.png', null));
    }

    /**
     * Hash a stable single storage name for a specific media.
     *
     * @see \ArchiveRepertory\File\FileManager::hashStorageName()
     *
     * @param Media $media
     * @return string
     */
    protected function hashStorageName(Media $media)
    {
        $storageName = substr(hash('sha256', $media->getId() . '/' . $media->getSource()), 0, 40);
        return $storageName;
    }
}
