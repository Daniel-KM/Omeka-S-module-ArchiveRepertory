<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Omeka\Entity\Media;

/**
 * Guarantees the core invariant of the module: whenever a file is relocated,
 * whether by this module or triggered by another module or the API, the file is
 * never lost and the name stored in database always matches the file on disk.
 */
class FileRelocationTest extends AbstractArchiveRepertoryTestCase
{
    /**
     * @var \Omeka\Api\Representation\ItemRepresentation
     */
    protected $item;

    public function setUp(): void
    {
        parent::setUp();
        $this->item = $this->createItem([]);
    }

    /**
     * Upload a single file into the item and return its stored media id.
     */
    protected function uploadFile(string $name): int
    {
        $this->editItem($this->item->id(), [
            'o:media' => [$this->uploadMedia(0)],
        ], $this->buildFiles([0 => $name]));
        $this->assertResponseStatusCode(302);
        $item = $this->api()->read('items', $this->item->id())->getContent();
        $medias = $item->media();
        return (int) $medias[count($medias) - 1]->id();
    }

    protected function mediaEntity(int $mediaId): Media
    {
        // Read through a fresh application so the entity reflects the database.
        return $this->getEntityManager()->find(Media::class, $mediaId);
    }

    /**
     * When metadata changes the target folder, the file (and its derivatives)
     * must be physically moved: present at the new place, gone from the old,
     * content preserved and database in sync.
     */
    public function testRelocationOnFolderChangeKeepsFile(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $mediaId = $this->uploadFile('photo.png');
        $itemId = $this->item->id();
        $oldFilename = $itemId . '/photo.png';

        $this->assertSame($oldFilename, $this->mediaEntity($mediaId)->getFilename());
        $this->assertMediaFileConsistency($oldFilename);
        $this->assertDerivativesExist($oldFilename);

        // Another change (here a title used as folder) relocates the file
        // without re-uploading it.
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_convert', 'full');
        $this->editItem($itemId, [
            'dcterms:title' => [$this->literal(1, 'New Folder')],
            'o:media' => [$this->existingMedia($mediaId)],
        ]);
        $this->assertResponseStatusCode(302);

        $newFilename = 'New_Folder/photo.png';
        $media = $this->mediaEntity($mediaId);
        $this->assertSame($newFilename, $media->getFilename(), 'Database name not updated.');
        $this->assertMediaFileConsistency($newFilename);
        $this->assertDerivativesExist($newFilename);

        // Nothing left behind.
        $this->assertFileDoesNotExist($this->storePath('original', $oldFilename), 'Old original was not moved.');
        $this->assertDerivativesAbsent($oldFilename);
    }

    /**
     * Re-saving without any folder change must be a no-op: no move, no loss,
     * stable database name.
     */
    public function testResaveWithoutChangeIsStable(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $mediaId = $this->uploadFile('photo.png');
        $filename = $this->item->id() . '/photo.png';
        $this->assertMediaFileConsistency($filename);

        $this->editItem($this->item->id(), [
            'o:media' => [$this->existingMedia($mediaId)],
        ]);
        $this->assertResponseStatusCode(302);

        $this->assertSame($filename, $this->mediaEntity($mediaId)->getFilename());
        $this->assertMediaFileConsistency($filename);
    }

    /**
     * If the original file is missing (deleted elsewhere, or an undetected
     * error during a previous conversion), the module must not update the
     * database name to a place where no file exists: the stored name stays
     * consistent with what is actually on disk and an error is reported.
     */
    public function testMissingOriginalDoesNotBreakConsistency(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $mediaId = $this->uploadFile('photo.png');
        $itemId = $this->item->id();
        $filename = $itemId . '/photo.png';

        // Simulate an external removal of the original file.
        unlink($this->storePath('original', $filename));

        // Trigger a relocation attempt.
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_convert', 'full');
        $this->editItem($itemId, [
            'dcterms:title' => [$this->literal(1, 'New Folder')],
            'o:media' => [$this->existingMedia($mediaId)],
        ]);
        $this->assertResponseStatusCode(302);

        // The database name is unchanged (no move claimed for a missing file).
        $this->assertSame($filename, $this->mediaEntity($mediaId)->getFilename());
        // No file was created at the hypothetical new location.
        $this->assertFileDoesNotExist($this->storePath('original', 'New_Folder/photo.png'));
        // A missing local original is legitimate: a warning, not an error.
        $this->assertNotEmpty($this->warningMessages(), 'A missing original should raise a warning.');
    }

    /**
     * A dry run (validateOnly, as used by module Contribute to validate a
     * contribution) must not move any file nor flush: the file stays in place
     * and the database name is unchanged, even though the proposed metadata
     * would have changed the target folder.
     */
    public function testValidateOnlyDoesNotMoveFileNorFlush(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $mediaId = $this->uploadFile('photo.png');
        $itemId = $this->item->id();
        $filename = $itemId . '/photo.png';
        $this->assertMediaFileConsistency($filename);

        // Simulate a contribution validation: the proposed title would move the
        // file to another folder, but the resource must not be persisted.
        $this->settings()->set('archiverepertory_item_folder', 1);
        $this->settings()->set('archiverepertory_item_convert', 'full');
        $this->api()->update('items', $itemId, [
            'dcterms:title' => [$this->literal(1, 'New Folder')],
        ], [], [
            'isPartial' => true,
            'flushEntityManager' => false,
            'validateOnly' => true,
        ]);
        $this->getEntityManager()->clear();

        // File untouched, database name unchanged, no phantom file created.
        $this->assertMediaFileConsistency($filename);
        $this->assertSame($filename, $this->mediaEntity($mediaId)->getFilename());
        $this->assertFileDoesNotExist($this->storePath('original', 'New_Folder/photo.png'));
    }

    /**
     * A duplicate target name must be suffixed, so no existing file is ever
     * overwritten or lost.
     */
    public function testDuplicateTargetIsSuffixedNotOverwritten(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->editItem($this->item->id(), [
            'o:media' => [$this->uploadMedia(0), $this->uploadMedia(1)],
        ], $this->buildFiles([0 => 'photo.png', 1 => 'photo.png']));
        $this->assertResponseStatusCode(302);

        $item = $this->api()->read('items', $this->item->id())->getContent();
        $itemId = $this->item->id();
        $filenames = $this->mediaFilenames($item);
        $this->assertEquals([
            $itemId . '/photo.png',
            $itemId . '/photo.1.png',
        ], $filenames);
        foreach ($filenames as $filename) {
            $this->assertMediaFileConsistency($filename);
        }
    }
}
