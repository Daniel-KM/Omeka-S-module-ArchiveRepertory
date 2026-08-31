<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;

class FileManagerTest extends AbstractArchiveRepertoryTestCase
{
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

    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();

        $itemSet = $this->api()->create('item_sets', [])->getContent();
        $this->createdItemSetIds[] = $itemSet->id();
        $this->itemSet = $entityManager->find(ItemSet::class, $itemSet->id());

        $item = $this->api()->create('items', ['o:item_set' => [$itemSet->id()]])->getContent();
        $this->createdItemIds[] = $item->id();
        $this->item = $entityManager->find(Item::class, $item->id());

        $filename = pathinfo($this->source, PATHINFO_BASENAME);
        $media = new Media();
        $media->setItem($this->item);
        $media->setIngester('upload');
        $media->setRenderer('file');
        $media->setSource($filename);
        $media->setMediaType($this->file['media_type']);
        $media->setExtension($this->file['extension']);
        $media->setHasOriginal(1);
        $media->setHasThumbnails(1);
        $media->setStorageId($this->hashStorageName($media));
        $this->media = $media;
    }

    public function testKeepOriginalFileName(): void
    {
        $this->settings()->set('archiverepertory_media_convert', 'hash');

        $this->assertNotEquals('image_test', $this->fileManager()->getStorageId($this->media));
    }

    public function testDontKeepOriginalName(): void
    {
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->assertEquals(
            'image_test',
            $this->fileManager()->getStorageId($this->media)
        );
    }

    public function testAddItemIdWithoutOriginalName(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'hash');

        $this->assertEquals(
            $this->item->getId() . '/' . $this->media->getStorageId(),
            $this->fileManager()->getStorageId($this->media)
        );
    }

    public function testAddItemIdAndOriginalName(): void
    {
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->assertEquals(
            $this->item->getId() . '/image_test',
            $this->fileManager()->getStorageId($this->media)
        );
    }

    public function testAddItemSetIdAndItemIdAndOriginalName(): void
    {
        $this->settings()->set('archiverepertory_item_set_folder', 'id');
        $this->settings()->set('archiverepertory_item_folder', 'id');
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $this->assertEquals(
            $this->itemSet->getId() . '/' . $this->item->getId() . '/image_test',
            $this->fileManager()->getStorageId($this->media)
        );
    }

    public function testInsertDuplicateFile(): void
    {
        $this->settings()->set('archiverepertory_media_convert', 'keep');

        $originalPath = $this->storePath('original');
        touch($originalPath . DIRECTORY_SEPARATOR . 'photo.png');

        $this->assertEquals(
            'photo.1.png',
            $this->fileManager()->getSingleFilenameBypassProtectedMethod('photo.png', null)
        );

        touch($originalPath . DIRECTORY_SEPARATOR . 'photo.1.png');
        $this->assertEquals(
            'photo.2.png',
            $this->fileManager()->getSingleFilenameBypassProtectedMethod('photo.png', null)
        );
    }

    /**
     * A name reduced to dots must not be allowed to escape the store.
     */
    public function testSanitizeNameRejectsPathTraversal(): void
    {
        $fileManager = $this->fileManager();
        $this->assertSame('', $fileManager->sanitizeNameBypassProtectedMethod('..'));
        $this->assertSame('', $fileManager->sanitizeNameBypassProtectedMethod('.'));
        $this->assertSame('', $fileManager->sanitizeNameBypassProtectedMethod('...'));
        // Slashes are stripped, so a compound path cannot form directory
        // segments: it collapses to a single harmless literal name.
        $this->assertStringNotContainsString('/', $fileManager->sanitizeNameBypassProtectedMethod('../../etc'));
    }

    public function testSanitizeNameRemovesSeparatorsButKeepsName(): void
    {
        $fileManager = $this->fileManager();
        $this->assertSame('etc passwd', $fileManager->sanitizeNameBypassProtectedMethod('/etc/passwd'));
        $this->assertSame('name.ext', $fileManager->sanitizeNameBypassProtectedMethod('name.ext'));
    }

    public function testSanitizeNameStripsSurroundingDots(): void
    {
        $fileManager = $this->fileManager();
        // A leading dot would make a hidden file, blocked by most web servers.
        $this->assertSame('hidden.png', $fileManager->sanitizeNameBypassProtectedMethod('.hidden.png'));
        $this->assertSame('htaccess', $fileManager->sanitizeNameBypassProtectedMethod('.htaccess'));
        $this->assertSame('name', $fileManager->sanitizeNameBypassProtectedMethod('..name..'));
    }

    public function testStorageIdNeverStartsWithDot(): void
    {
        $this->settings()->set('archiverepertory_media_convert', 'keep');
        $this->media->setSource('.hidden.png');

        $this->assertSame('hidden', $this->fileManager()->getStorageId($this->media));
    }

    public function testConvertFilenameToAscii(): void
    {
        $fileManager = $this->fileManager();
        $this->assertSame('ete', $fileManager->convertFilenameToBypassProtectedMethod('été', 'full'));
        $this->assertSame('a_b', $fileManager->convertFilenameToBypassProtectedMethod('a b', 'spaces'));
    }

    public function testSanitizeNameNormalizesToUtf8(): void
    {
        $fileManager = $this->fileManager();
        // A copyright sign encoded as a lone Latin-1/Windows-1252 byte becomes
        // a valid UTF-8 name (kept, not lost).
        $result = $fileManager->sanitizeNameBypassProtectedMethod('photo' . chr(0xA9) . '.png');
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertSame('photo©.png', $result);
    }

    public function testStorageIdKeepModeIsValidUtf8(): void
    {
        $this->settings()->set('archiverepertory_media_convert', 'keep');
        // Latin-1 encoded source name.
        $this->media->setSource('photo' . chr(0xA9) . '.png');

        $storageId = $this->fileManager()->getStorageId($this->media);
        $this->assertTrue(mb_check_encoding($storageId, 'UTF-8'));
        $this->assertSame('photo©', $storageId);
    }

    public function testTransliterationToAscii(): void
    {
        $fileManager = $this->fileManager();
        // Latin scripts: handled by both the ICU and the iconv paths.
        $this->assertSame('cafe', $fileManager->convertFilenameToBypassProtectedMethod('café', 'full'));
        $this->assertSame('Strasse', $fileManager->convertFilenameToBypassProtectedMethod('Straße', 'full'));
        $this->assertSame('OEuvre', $fileManager->convertFilenameToBypassProtectedMethod('Œuvre', 'full'));
        // The copyright sign folds to "(C)"; parentheses are then removed.
        $this->assertSame('photo_C_', $fileManager->convertFilenameToBypassProtectedMethod('photo©', 'full'));
        // A lone Latin-1 byte (invalid UTF-8) is not transliterated but the
        // name is not lost either.
        $this->assertSame('photo_', $fileManager->convertFilenameToBypassProtectedMethod('photo' . chr(0xA9), 'full'));

        if (!class_exists(\Transliterator::class)) {
            $this->markTestSkipped('ext-intl is required to transliterate non-Latin scripts.');
        }
        $this->assertSame('Privet', $fileManager->convertFilenameToBypassProtectedMethod('Привет', 'full'));
        $this->assertSame('Ellada', $fileManager->convertFilenameToBypassProtectedMethod('Ελλάδα', 'full'));
        $this->assertSame('Dang', $fileManager->convertFilenameToBypassProtectedMethod('Đặng', 'full'));
    }

    public function testGetBaseNameKeepsLeadingDotFile(): void
    {
        $fileManager = $this->fileManager();
        $this->assertSame('.htaccess', $fileManager->getBaseName('.htaccess'));
        $this->assertSame('photo', $fileManager->getBaseName('photo.png'));
        $this->assertSame('1706/alpha', $fileManager->getBaseName('1706/alpha.jpg'));
    }

    protected function hashStorageName(Media $media): string
    {
        return substr(hash('sha256', $media->getId() . '/' . $media->getSource()), 0, 40);
    }
}
