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
    $this->dispatch('/admin/module/configure?id=ArchiveDirectory');

    $this->assertXPathQuery('//div//select[@id="resource-class-select"]//option[@value="id"]');
  }

  /** @test */
  public function postCssShouldBeSaved() {
    $this->postDispatch('/admin/module/configure?id=ArchiveDirectory', ['css' => "h1{display:none;}"]);
    $this->assertEquals("h1 {\ndisplay:none\n}",$this->getApplicatinServiceLocator()->get('Omeka\Settings')->get('css_editor_css'));
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