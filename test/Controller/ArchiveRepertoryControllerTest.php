<?php

namespace OmekaTest\Controller;

use Omeka\Test\AbstractHttpControllerTestCase;


class ArchiveRepertoryAdminControllerTest extends AbstractHttpControllerTestCase
{
  protected $site_test = true;
  protected $traceError = true;
  public function setUp() {

    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('ArchiveRepertory');
    $manager->install($module);

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

}


class ArchiveRepertorySiteControllerTest  extends AbstractHttpControllerTestCase{
  public function setUp() {

    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('ArchiveRepertory');
    $manager->install($module);
    $this->site_test=$this->addSite('test');
    parent::setUp();
    $this->connectAdminUser();
  }

  public function tearDown() {
    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('ArchiveRepertory');
    $manager->uninstall($module);
    $this->cleanTable('site');
  }
  /** @test */
  public function displayPublicPageShouldLoadCss() {
    $this->setSettings('css_editor_css','h1 {display:none}');
    $this->dispatch('/s/test');
    $this->assertXPathQuery('//style[@type="text/css"][@media="screen"]');
    $this->assertContains('h1 {display:none}',$this->getResponse()->getContent());
  }



  /** @test */
  public function displayPublicSitePageShouldLoadSpecificCss() {
    $this->setSettings('css_editor_css','h1 {display:none}');
    $this->getSiteSettings()->set('css_editor_css', 'h2 { color:black;}');
    $this->dispatch('/s/test');
    $this->assertXPathQuery('//style[@type="text/css"][@media="screen"]');
    $this->assertContains('h2 { color:black;}',$this->getResponse()->getContent());
  }

  protected function getSiteSettings() {
    $settings=$this->getApplicationServiceLocator()->get('Omeka\SiteSettings');
    $settings->setSite($this->getApplicationServiceLocator()->get('Omeka\EntityManager')->find('Omeka\Entity\Site',$this->site_test->getId()));
    return $settings;

  }

  /** @test */
  public function postCssShouldBeSavedForASite() {

    $this->postDispatch('/admin/module/configure?id=ArchiveRepertory', ['css' => "h1{display:inline;}", 'site' =>$this->site_test->getId()]);
    $this->assertEquals("h1 {\ndisplay:inline\n}",$this->getSiteSettings()->get('css_editor_css'));
  }



}