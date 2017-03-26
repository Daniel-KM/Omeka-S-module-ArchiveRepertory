<?php

namespace OmekaTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Omeka\Mvc\Controller\Plugin\Messenger;
use ArchiveRepertoryTest\MockUpload;

class ArchiveRepertoryAdminControllerTest extends OmekaControllerTestCase
{
    protected $item;
    protected $fileUrl;

    public function setUp()
    {
        parent::setUp();

        try {
            $this->loginAsAdmin();

            $this->overrideConfig();

            $this->setDefaultSettings();
        } catch (\Exception $e) {
            error_log($e);
        }
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

    protected function setDefaultSettings()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $this->item = $api->create('items', [])->getContent();

        foreach ($this->settingsProvider() as $data) {
            $this->setSettings($data[0], $data[1]);
        }
    }

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->id());
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
        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', true);
        $settings->set('archive_repertory_item_prefix', 'prefix:');

        $this->fileUrl = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . '_files'
            . DIRECTORY_SEPARATOR . 'image_test.png';

        $files = new \Zend\Stdlib\Parameters([
            'file' => [
                1 => [
                    'name' => 'photo.png',
                    'type' => 'image/png',
                    'tmp_name' => $this->fileUrl,
                    'size' => filesize($this->fileUrl),
                    'error' => 0,
                    'content' => file_get_contents($this->fileUrl),
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
        $this->assertEquals('Other_modified_title/photo.png', $medias[0]->filename());
    }

    public function testDuplicateNameShouldMoveFileWithAnotherName()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', true);
        $settings->set('archive_repertory_item_prefix', '');

        $this->postDipatchFiles('My modified title', 'photo.png', 'photo.png');
        $result_expected = ['My_modified_title/photo.png', 'My_modified_title/photo.1.png'];
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
        $messenger = new Messenger;
        $messages = $messenger->get();
        return $messages[Messenger::SUCCESS][0];
    }

    public function testDifferentNameShouldMoveFileWithAnotherName()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', true);
        $settings->set('archive_repertory_item_prefix', 'nonexisting');

        $this->postDipatchFiles('My modified title', 'photo.png', 'another_file.png');
        $result_expected = [
            $this->item->id() . '/photo.png',
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

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', false);
        $settings->set('archive_repertory_item_prefix', '');

        $this->postDipatchFiles('Previous title', 'photo.1.png', 'another_file.png');
        $settings->set('archive_repertory_file_keep_original_name', true);

        $existing_medias = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $existing_medias[$media->id()] = [
                'o:id' => $media->id(),
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

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', true);
        $settings->set('archive_repertory_item_prefix', '');

        $this->postDipatchFiles('Previous title', 'photo.1.png', 'another_file.png');
        $settings->set('archive_repertory_file_keep_original_name', false);
        $existing_medias = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $existing_medias[$media->id()] = [
                'o:id' => $media->id(),
                'o:is_public' => 1,
            ];
        }

        $this->postDipatchFiles('My title', 'photo2.png', 'another_file3.png', 10, 20, $existing_medias);

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

    protected function postDipatchFiles($title, $name_file1, $name_file2, $id1 = 0, $id2 = 1, $existing_ids = [])
    {
        $this->fileUrl = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . '_files'
            . DIRECTORY_SEPARATOR . 'image_test.png';
        $this->fileUrl2 = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . '_files'
            . DIRECTORY_SEPARATOR . 'image_test.save.png';
        $files = new \Zend\Stdlib\Parameters([
            'file' => [
                $id1 => [
                    'name' => $name_file1,
                    'type' => 'image/png',
                    'tmp_name' => $this->fileUrl,
                    'size' => filesize($this->fileUrl),
                    'error' => 0,
                    'content' => file_get_contents($this->fileUrl),
                ],
                $id2 => [
                    'name' => $name_file2,
                    'type' => 'image/png',
                    'tmp_name' => $this->fileUrl2,
                    'size' => 1,
                    'error' => 0,
                    'content' => file_get_contents($this->fileUrl),
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
