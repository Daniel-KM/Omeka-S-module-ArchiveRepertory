<?php declare(strict_types=1);

namespace ArchiveRepertoryTest\Controller;

use ArchiveRepertoryTest\AbstractArchiveRepertoryTestCase;
use Omeka\Api\Representation\ItemRepresentation;

class ArchiveRepertoryControllerTest extends AbstractArchiveRepertoryTestCase
{
    /**
     * @var ItemRepresentation
     */
    protected $item;

    public function setUp(): void
    {
        parent::setUp();
        $this->item = $this->createItem([]);
    }

    public function testConfigFormIsOk(): void
    {
        $this->dispatch('/admin/module/configure?id=ArchiveRepertory');
        $this->assertResponseStatusCode(200);
    }

    public function testPostItemShouldMoveFileInAnotherDirectory(): void
    {
        // 1 is Dublin Core Title.
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_prefix', 'prefix:');
        $this->settings()->set('archiverepertory_item_convert', 'full');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->editItem($this->item->id(), [
            'dcterms:title' => [
                $this->literal(1, 'My modified title'),
                $this->literal(1, 'prefix:Other modified title'),
            ],
            'o:media' => [$this->uploadMedia(1)],
        ], $this->buildFiles([1 => 'image_test.png']));

        $this->assertResponseStatusCode(302);

        $item = $this->api()->read('items', $this->item->id())->getContent();
        $medias = $item->media();
        $this->assertCount(1, $medias);
        $this->assertEquals('Other_modified_title/image_test.png', $medias[0]->filename());
        // The physical file really is where the database says.
        $media = $this->getEntityManager()->find(\Omeka\Entity\Media::class, $medias[0]->id());
        $this->assertMediaFileConsistency($media);
    }

    public function testDuplicateNameShouldMoveFileWithAnotherName(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_convert', 'full');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->editItem($this->item->id(), [
            'dcterms:title' => [$this->literal(1, 'My modified title')],
            'o:media' => [$this->uploadMedia(0), $this->uploadMedia(1)],
        ], $this->buildFiles([0 => 'image_test.png', 1 => 'image_test.png']));

        $this->assertResponseStatusCode(302);

        $item = $this->api()->read('items', $this->item->id())->getContent();
        $filenames = $this->mediaFilenames($item);
        $this->assertEquals([
            'My_modified_title/image_test.png',
            'My_modified_title/image_test.1.png',
        ], $filenames);

        // Both files exist on disk, neither overwritten nor lost.
        foreach ($filenames as $filename) {
            $this->assertMediaFileConsistency($filename);
        }
        $this->assertEquals('Item successfully updated', $this->firstSuccessMessage());
    }

    public function testDifferentNameShouldKeepBothFilesInIdFolder(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_prefix', 'nonexisting');
        $this->settings()->set('archiverepertory_item_convert', 'full');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        // The prefix matches nothing, so the folder falls back to the item id.
        $this->editItem($this->item->id(), [
            'dcterms:title' => [$this->literal(1, 'My modified title')],
            'o:media' => [$this->uploadMedia(0), $this->uploadMedia(1)],
        ], $this->buildFiles([0 => 'image_test.png', 1 => 'another_file.png']));

        $this->assertResponseStatusCode(302);

        $item = $this->api()->read('items', $this->item->id())->getContent();
        $itemId = $this->item->id();
        $this->assertEquals([
            $itemId . '/image_test.png',
            $itemId . '/another_file.png',
        ], $this->mediaFilenames($item));
        foreach ($this->mediaFilenames($item) as $filename) {
            $this->assertMediaFileConsistency($filename);
        }
    }
}
