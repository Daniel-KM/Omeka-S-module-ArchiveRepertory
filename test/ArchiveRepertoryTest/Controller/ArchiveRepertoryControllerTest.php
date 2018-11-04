<?php
namespace ArchiveRepertoryTest\Controller;

use ArchiveRepertoryTest\MockUpload;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Mvc\Controller\Plugin\Messenger;
use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Symfony\Component\Filesystem\Filesystem;

class ArchiveRepertoryControllerTest extends OmekaControllerTestCase
{
    private $umask;
    protected $filesystem;
    protected $workspace;

    /**
     * @var ItemRepresentation
     */
    protected $item;

    /**
     * @var array
     */
    protected $file;

    protected $source;
    protected $tempname;

    public function setUp()
    {
        parent::setUp();

        try {
            $this->loginAsAdmin();

            $this->prepareArchiveDir();
            mkdir($this->workspace . DIRECTORY_SEPARATOR . 'files', 0777, true);
            mkdir($this->workspace . DIRECTORY_SEPARATOR . 'tmp', 0777, true);

            $this->overrideConfig();

            $this->setDefaultSettings();
        } catch (\Exception $e) {
            error_log($e);
        }

        // $services = $this->getServiceLocator();

        $this->source = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . '_files'
            . DIRECTORY_SEPARATOR . 'image_test.png';

        $this->file['media_type'] = 'image/png';
        $this->file['size'] = filesize($this->source);
        $this->file['content'] = file_get_contents($this->source);

        $this->item = $this->api()->create('items', [])->getContent();

        $this->tempname = $this->workspace
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . md5($this->source . microtime(true) . '.' . mt_rand());
        copy($this->source, $this->tempname);
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
    }

    protected function setDefaultSettings()
    {
        foreach ($this->settingsProvider() as $data) {
            $this->setSettings($data[0], $data[1]);
        }
    }

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->id());

        $this->filesystem->remove($this->workspace);
        umask($this->umask);
    }

    public function settingsProvider()
    {
        return [
                ['archiverepertory_item_convert', 'false'],
                ['archiverepertory_item_prefix', 'prefix'],
                ['archiverepertory_item_folder', 'foldername'],
                ['archiverepertory_media_convert', 'keep'],
        ];
    }

    public function testConfigFormIsOk()
    {
        $this->dispatch('/admin/module/configure?id=ArchiveRepertory');
        $this->assertResponseStatusCode(200);
    }

    /**
     * @dataProvider settingsProvider
     */
    public function testPostConfigurationShouldBeSaved($name, $value)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $this->postDispatch('/admin/module/configure?id=ArchiveRepertory', [$name => $value]);
        $this->assertEquals($value, $settings->get($name));
    }

    public function testPostItemShouldMoveFileInAnotherDirectory()
    {
        // 1 is the Dublin Core Title.
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_prefix', 'prefix:');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $files = new \Zend\Stdlib\Parameters([
            'file' => [
                1 => [
                    'name' => pathinfo($this->source, PATHINFO_BASENAME),
                    'type' => $this->file['media_type'],
                    'tmp_name' => $this->tempname,
                    'size' => $this->file['size'],
                    'error' => 0,
                    'content' => $this->file['content'],
                ],
            ],
        ]);
        $this->getRequest()->setFiles($files);

        $this->postDispatch('/admin/item/' . $this->item->id() . '/edit', [
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

        $this->assertResponseStatusCode(302);

        $item = $this->api()->read('items', $this->item->id())->getContent();
        $medias = $item->media();
        $this->assertCount(1, $medias);
        $this->assertEquals('Other_modified_title/image_test.png', $medias[0]->filename());
    }

    public function testDuplicateNameShouldMoveFileWithAnotherName()
    {
        // 1 is the Dublin Core Title.
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_prefix', '');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->postDispatchFiles(
            'My modified title',
            'image_test.png',
            'image_test.png'
        );
        $resultExpected = [
            'My_modified_title/image_test.png',
            'My_modified_title/image_test.1.png',
        ];
        $item = $this->api()->read('items', $this->item->id())->getContent();
        $result = $this->getMediaFilenames($item);
        $this->assertEquals($resultExpected, $result);
        $this->assertEquals('Item successfully updated', $this->getMessengerFirstSuccessMessage());
    }

    protected function getMessengerFirstSuccessMessage()
    {
        $messenger = new Messenger();
        $messages = $messenger->get();
        return $messages[Messenger::SUCCESS][0];
    }

    public function testDifferentNameShouldMoveFileWithAnotherName()
    {
        // 1 is the Dublin Core Title.
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_prefix', 'nonexisting');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->postDispatchFiles(
            'My modified title',
            'image_test.png',
            'another_file.png'
        );
        $resultExpected = [
            $this->item->id() . '/image_test.png',
            $this->item->id() . '/another_file.png',
        ];
        $item = $this->api()->read('items', $this->item->id())->getContent();
        $result = $this->getMediaFilenames($item);
        $this->assertEquals($resultExpected, $result);
    }

    public function testDifferentFileShouldMoveFileWithAnotherName()
    {
        // 1 is the Dublin Core Title.
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_prefix', '');
        $this->settings()->set('archiverepertory_media_convert', 'hash');

        $this->postDispatchFiles(
            'Previous title',
            'image_test.1.png',
            'another_file.png'
        );

        $existingMedias = [];
        $item = $this->api()->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $existingMedias[$media->id()] = [
                'o:id' => $media->id(),
                'o:is_public' => 1,
            ];
        }

        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->postDispatchFiles(
            'My title',
            'image_test_2.png',
            'another_file3.png',
            10,
            20,
            $existingMedias,
            true
        );

        $resultExpected = [
            'My_title/image_test.1.png',
            'My_title/another_file.png',
            'My_title/image_test_2.png',
            'My_title/another_file3.png',
        ];
        $result = $this->getMediaFilenames($item);
        $this->assertEquals($resultExpected, $result);
    }

    public function testDifferentFileShouldMoveFileWithIdentifiers()
    {
        $this->settings()->set('archiverepertory_item_set_folder', '');
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_prefix', '');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->postDispatchFiles(
            'Previous title',
            'photo.1.png',
            'another_file.png'
        );

        $resultExpected = [
            'Previous_title/photo.1.png',
            'Previous_title/another_file.png',
        ];
        $item = $this->api()->read('items', $this->item->id())->getContent();
        $result = $this->getMediaFilenames($item);
        $this->assertEquals($resultExpected, $result);

        $existingMedias = [];
        foreach ($item->media() as $media) {
            $existingMedias[$media->id()] = [
                'o:id' => $media->id(),
                'o:is_public' => 1,
            ];
        }

        $this->postDispatchFiles(
            'My title',
            'photo2.png',
            'another_file3.png',
            10,
            20,
            $existingMedias,
            true
        );

        $resultExpected = [
            'My_title/photo.1.png',
            'My_title/another_file.png',
            'My_title/photo2.png',
            'My_title/another_file3.png',
        ];
        $item = $this->api()->read('items', $this->item->id())->getContent();
        $result = $this->getMediaFilenames($item);
        $this->assertEquals($resultExpected, $result);
    }

    protected function postDispatchFiles($title, $name_file1, $name_file2, $id1 = 0, $id2 = 1, $existingMedias = [], $viaApi = false)
    {
        $this->tempname1 = $this->workspace
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . md5($this->source . microtime(true) . '.' . mt_rand());
        copy($this->source, $this->tempname1);

        $this->tempname2 = $this->workspace
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . md5($this->source . microtime(true) . '.' . mt_rand());
        copy($this->source, $this->tempname2);

        $itemId = $this->item->id();

        $data = [
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
            'o:media' => array_merge($existingMedias, [
                $id1 => [
                    'o:ingester' => 'upload',
                    'file_index' => $id1,
                    'o:is_public' => 1,
                    'dcterms:title' => [
                        [
                            'type' => 'literal',
                            'property_id' => 1,
                            '@value' => 'media1',
                        ],
                    ],
                ],
                $id2 => [
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
        ];

        $files = [
            'file' => [
                $id1 => [
                    'name' => $name_file1,
                    'type' => $this->file['media_type'],
                    'tmp_name' => $this->tempname1,
                    'size' => $this->file['size'],
                    'error' => 0,
                    'content' => $this->file['content'],
                ],
                $id2 => [
                    'name' => $name_file2,
                    'type' => $this->file['media_type'],
                    'tmp_name' => $this->tempname2,
                    'size' => $this->file['size'],
                    'error' => 0,
                    'content' => $this->file['content'],
                ],
            ],
        ];

        if ($viaApi) {
            $this->api()->update('items', $itemId, $data, $files);
        } else {
            $data['csrf'] = (new \Zend\Form\Element\Csrf('csrf'))->getValue();
            $this->getRequest()->setFiles(new \Zend\Stdlib\Parameters($files));
            $this->postDispatch("/admin/item/$itemId/edit", $data);
        }
    }

    protected function getMediaFilenames($item)
    {
        $result = [];
        foreach ($item->media() as $media) {
            $result[] = $media->filename();
        }
        return $result;
    }
}
