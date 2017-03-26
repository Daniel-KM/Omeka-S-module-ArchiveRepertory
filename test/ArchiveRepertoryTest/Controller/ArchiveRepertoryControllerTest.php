<?php

namespace OmekaTest\Controller;

use ArchiveRepertoryTest\MockUpload;
use Exception;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Mvc\Controller\Plugin\Messenger;
use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Symfony\Component\Filesystem\Filesystem;

class ArchiveRepertoryAdminControllerTest extends OmekaControllerTestCase
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
        } catch (Exception $e) {
            error_log($e);
        }

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $this->source = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . '_files'
            . DIRECTORY_SEPARATOR . 'image_test.png';

        $this->file['media_type'] = 'image/png';
        $this->file['size'] = filesize($this->source);
        $this->file['content'] = file_get_contents($this->source);

        $this->item = $api->create('items', [])->getContent();

        $this->tempname = $this->workspace
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . md5($this->source . microtime(true) . '.' . mt_rand());
        copy($this->source, $this->tempname);
    }

    /**
     * @see Symfony\Component\Filesystem\Tests\FilesystemTestCase
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
        // Temp dir is configured by Omeka S.
        $config['temp_dir'] = $this->workspace;
        // Local dir is configured by the module.
        $config['local_dir'] = $this->workspace . DIRECTORY_SEPARATOR . 'files';
        // Local path is an added path to bypass the hardcoded OMEKA_PATH.
        $config['file_manager']['localpath'] = $this->workspace;
        $config = $services->setService('Config', $config);

        $services->setFactory('Omeka\File\LocalStore', 'ArchiveRepertoryTest\MockLocalStoreFactory');
        $services->setFactory('Omeka\File\Manager', 'ArchiveRepertoryTest\MockFileManagerFactory');
        $services->setAllowOverride(false);

        $fileManager = $services->get('Omeka\File\Manager');

        $mediaIngesterManager = $services->get('Omeka\MediaIngesterManager');
        $mediaIngesterManager->setAllowOverride(true);
        $mockUpload = new MockUpload($fileManager);
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
                ['archive_repertory_ingesters', ['upload' => []]],
                ['archive_repertory_item_convert', 'false'],
                ['archive_repertory_item_prefix', 'prefix'],
                ['archive_repertory_item_folder', 'foldername'],
                ['archive_repertory_file_keep_original_name', true],
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
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        // 1 is the Dublin Core Title.
        $settings->set('archive_repertory_item_set_folder', '');
        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_item_prefix', 'prefix:');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $files = new \Zend\Stdlib\Parameters([
            'file' => [
                1 => [
                    'name' => basename($this->source),
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

        $item = $api->read('items', $this->item->id())->getContent();
        $medias = $item->media();
        $this->assertCount(1, $medias);
        $this->assertEquals('Other_modified_title/image_test.png', $medias[0]->filename());
    }

    public function testDuplicateNameShouldMoveFileWithAnotherName()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        // 1 is the Dublin Core Title.
        $settings->set('archive_repertory_item_set_folder', '');
        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_item_prefix', '');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $this->postDispatchFiles('My modified title', 'image_test.png', 'image_test.png');
        $result_expected = [
            'My_modified_title/image_test.png',
            'My_modified_title/image_test.1.png',
        ];
        $result = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $result[] = $media->filename();
        }

        $this->assertEquals($result_expected, $result);
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
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        // 1 is the Dublin Core Title.
        $settings->set('archive_repertory_item_set_folder', '');
        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_item_prefix', 'nonexisting');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $this->postDispatchFiles('My modified title', 'image_test.png', 'another_file.png');
        $result_expected = [
            $this->item->id() . '/image_test.png',
            $this->item->id() . '/another_file.png',
        ];
        $result = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $result[] = $media->filename();
        }
        $this->assertEquals($result_expected, $result);
    }

    public function testDifferentFileShouldMoveFileWithAnotherName()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        // 1 is the Dublin Core Title.
        $settings->set('archive_repertory_item_set_folder', '');
        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_item_prefix', '');
        $settings->set('archive_repertory_file_keep_original_name', false);

        $this->postDispatchFiles('Previous title', 'image_test.1.png', 'another_file.png');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $existing_medias = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $existing_medias[$media->id()] = [
                'o:id' => $media->id(),
                'o:is_public' => 1,
            ];
        }

        $this->postDispatchFiles('My title', 'image_test_2.png', 'another_file3.png',
            10, 20, $existing_medias);

        $result_expected = [
            'My_title/image_test.1.png',
            'My_title/another_file.png',
            'My_title/image_test_2.png',
            'My_title/another_file3.png',
        ];

        $result = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $result[] = $media->filename();
        }

        $this->assertEquals($result_expected, $result);
    }

    public function testDifferentFileShouldMoveFileWithIds()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_set_folder', '');
        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_item_prefix', '');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $this->postDispatchFiles('Previous title', 'photo.1.png', 'another_file.png');
        $settings->set('archive_repertory_file_keep_original_name', false);
        $existing_medias = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $existing_medias[$media->id()] = [
                'o:id' => $media->id(),
                'o:is_public' => 1,
            ];
        }

        $this->postDispatchFiles('My title', 'photo2.png', 'another_file3.png', 10, 20, $existing_medias);

        $result_expected = [
            'My_title/photo.1.png',
            'My_title/another_file.png',
            'My_title/photo2.png',
            'My_title/another_file3.png',
        ];
        $result = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $this->assertEquals(1, preg_match('/^My_title\/.*\.png/', $media->filename()));
        }
    }

    protected function postDispatchFiles($title, $name_file1, $name_file2, $id1 = 0, $id2 = 1, $existing_ids = [])
    {
        $this->tempname1 = $this->workspace
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . md5($this->source . microtime(true) . '.' . mt_rand());
        copy($this->source, $this->tempname1);

        $this->tempname2 = $this->workspace
            . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . md5($this->source . microtime(true) . '.' . mt_rand());
        copy($this->source, $this->tempname2);

        $files = new \Zend\Stdlib\Parameters([
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
        ]);
        $this->getRequest()->setFiles($files);

        $itemId = $this->item->id();
        $this->postDispatch("/admin/item/$itemId/edit", [
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
            'o:media' => array_merge($existing_ids, [
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
            'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
        ]);
    }
}
