<?php

namespace OmekaTest\Controller;

use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Test\AbstractHttpControllerTestCase;


class ArchiveRepertoryAdminControllerTest extends AbstractHttpControllerTestCase
{
  protected $site_test = true;
  protected $traceError = true;
  protected $item;
  public function setUp() {

    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('ArchiveRepertory');
    $manager->install($module);
    $this->setDefaultSettings();
    parent::setUp();
    $this->connectAdminUser();

  }

  public function tearDown() {
    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('ArchiveRepertory');
    $manager->uninstall($module);

  }


  public function testTextAreaShouldBeDisplayOnConfigure()
  {
    $this->dispatch('/admin/module/configure?id=ArchiveRepertory');

    $this->assertXPathQuery('//select[@id="archive_repertory_collection_folder"]');
  }

  public function datas() {
      return [
              ['archive_repertory_collection_convert', 'pre'],
              ['archive_repertory_collection_prefix', 'pre'],
              ['archive_repertory_collection_folder', 'pre'],
              ['archive_repertory_item_convert', 'false'],
              ['archive_repertory_item_prefix', 'prefix'],
              ['archive_repertory_item_folder', 'foldername'],
              ['archive_repertory_file_keep_original_name' ,true],
              ['archive_repertory_file_convert', 'Full'],
              ['archive_repertory_file_base_original_name' , false],
              ['archive_repertory_derivative_folders' , 'derive'],
              ['archive_repertory_move_process', 'omeka'],
              ['archive_repertory_download_max_free_download' , 19],
              ['archive_repertory_legal_text' , 'I disagree with terms of use.']
      ];
  }

    /**
   * @test
   * @dataProvider datas
   */
  public function postConfigurationShouldBeSaved($name,$value) {

    $this->postDispatch('/admin/module/configure?id=ArchiveRepertory', [$name => $value]);
    $this->assertEquals($value,$this->getApplicationServiceLocator()->get('Omeka\Settings')->get($name));

  }

  protected function setDefaultSettings() {
    $this->item = new Item;
    $media = new Media;
    $url ='/test/_files/image_test.png';
    $fileUrl = dirname(dirname(__FILE__)).$url;
    $media->setFilename($url);
    $media->setItem($this->item);
    foreach ($this->datas() as $data) {
          $this->setSettings($data[0],$data[1]);
      }

    $this->persistAndSave($this->item);

   }


   /** @test */
   public function postItemShouldMoveFile() {
       $this->postDispatch('/admin/item/4/edit', ['dcterms:title[0][@value]' => "My item title"]);
       $this->assertEquals($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->getRepository('Omeka\Entity\Media')->findBy(['item_id' => $this->item->getId()]), []);
  }



}