<?php

namespace OmekaTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;
use Omeka\Mvc\Controller\Plugin\Messenger;
use ArchiveRepertory\Media\Ingester\UploadAnywhere;
use ArchiveRepertoryTest\MockUpload;

class ArchiveRepertoryAdminControllerTest extends OmekaControllerTestCase
{
    protected $item;

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
        $fileWriter = $services->get('ArchiveRepertory\FileWriter');

        $mediaIngesterManager->setAllowOverride(true);
        $mockUpload = new MockUpload($fileManager);
        $mockUpload->setFileWriter($fileWriter);
        $mediaIngesterManager->setService('upload', $mockUpload);
        $mediaIngesterManager->setAllowOverride(false);
    }

    protected function setDefaultSettings()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $this->item = $api->create('items', [])->getContent();

        foreach ($this->datas() as $data) {
            $this->setSettings($data[0], $data[1]);
        }
    }

    public function tearDown()
    {
        $this->api()->delete('items', $this->item->id());
    }

    public function datas() {
        return [
                ['archive_repertory_item_convert', 'false'],
                ['archive_repertory_item_prefix', 'prefix'],
                ['archive_repertory_item_folder', 'foldername'],
                ['archive_repertory_file_keep_original_name' ,true],
                ['archive_repertory_derivative_folders' , 'derive'],
        ];
    }

    public function testConfigFormIsOk()
    {
        $this->dispatch('/admin/module/configure?id=ArchiveRepertory');
        $this->assertResponseStatusCode(200);
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


    /** @test */
    public function postItemShouldMoveFileInAnotherDirectory()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', '1');
        $settings->set('archive_repertory_item_prefix', 'prefix:');

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
        $media = $item->media();
        $this->assertCount(1, $media);
        $this->assertEquals('Other_modified_title/photo.png', $media[0]->filename());
    }

    /** @test */
    public function duplicateNameShouldMoveFileWithAnotherName()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', '1');
        $settings->set('archive_repertory_item_prefix', '');

        $this->postDipatchFiles('My modified title','photo.png', 'photo.png');
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
        return $messages[Messenger::SUCCESS][0][0];
    }

    /** @test */
    public function differentNameShouldMoveFileWithAnotherName()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', '1');
        $settings->set('archive_repertory_item_prefix', 'nonexisting');

        $this->postDipatchFiles('My modified title','photo.png', 'another_file.png');
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

    /** @test */
    public function differentFileShouldMoveFileWithAnotherName()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', '0');
        $settings->set('archive_repertory_item_prefix', '');

        $this->postDipatchFiles('Previous title','photo.1.png', 'another_file.png');
        $settings->set('archive_repertory_file_keep_original_name', '1');

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

    /** @test */
    public function differentFileShouldMoveFileWithIds()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $settings->set('archive_repertory_item_folder', 1);
        $settings->set('archive_repertory_file_keep_original_name', '1');
        $settings->set('archive_repertory_item_prefix', '');

        $this->postDipatchFiles('Previous title','photo.1.png', 'another_file.png');
        $settings->set('archive_repertory_file_keep_original_name', '0');
        $existing_medias = [];
        $item = $api->read('items', $this->item->id())->getContent();
        foreach ($item->media() as $media) {
            $existing_medias[$media->id()] = [
                'o:id' => $media->id(),
                'o:is_public' => 1
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

    protected function postDipatchFiles($title, $name_file1, $name_file2, $id1=0, $id2=1, $existing_ids = [])
    {
        $this->_fileUrl = dirname(dirname(__FILE__)).'/_files/image_test.png';
        $this->_fileUrl2 = dirname(dirname(__FILE__)).'/_files/image_test.save.png';
        $files = new \Zend\Stdlib\Parameters([
            'file' => [
                $id1 => [
                    'size' => 1000000,
                    'name' => $name_file1,
                    'type' => 'image/png',
                    'tmp_name' => $this->_fileUrl,
                    'size' => 1,
                    'error' => 0,
                    'content' => file_get_contents($this->_fileUrl),
                ],
                $id2 => [
                    'size' => 100000,
                    'name' => $name_file2,
                    'type' => 'image/png',
                    'tmp_name' => $this->_fileUrl2,
                    'size' => 1,
                    'error' => 0,
                    'content' => file_get_contents($this->_fileUrl),
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
                    ]
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
