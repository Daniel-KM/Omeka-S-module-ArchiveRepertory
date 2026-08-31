<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use ArchiveRepertory\Helpers;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    /**
     * @dataProvider basenameProvider
     */
    public function testPathinfoBasename(string $path, string $expected): void
    {
        $this->assertSame($expected, Helpers::pathinfo($path, PATHINFO_BASENAME));
    }

    public function basenameProvider(): array
    {
        return [
            ['/a/b/c.png', 'c.png'],
            ['dir/éfile.png', 'éfile.png'],
            ['éfile.png', 'éfile.png'],
            ['a/b/', 'b'],
        ];
    }

    /**
     * @dataProvider filenameProvider
     */
    public function testPathinfoFilename(string $path, string $expected): void
    {
        $this->assertSame($expected, Helpers::pathinfo($path, PATHINFO_FILENAME));
    }

    public function filenameProvider(): array
    {
        return [
            ['c.png', 'c'],
            ['a/b.tar.gz', 'b.tar'],
            // A leading dot is not an extension separator.
            ['.htaccess', '.htaccess'],
            ['dir/.htaccess', '.htaccess'],
        ];
    }

    /**
     * @dataProvider extensionProvider
     */
    public function testPathinfoExtension(string $path, string $expected): void
    {
        $this->assertSame($expected, Helpers::pathinfo($path, PATHINFO_EXTENSION));
    }

    public function extensionProvider(): array
    {
        return [
            ['c.png', 'png'],
            ['noext', ''],
            ['.htaccess', ''],
        ];
    }

    /**
     * @dataProvider dirnameProvider
     */
    public function testPathinfoDirname(string $path, string $expected): void
    {
        $this->assertSame($expected, Helpers::pathinfo($path, PATHINFO_DIRNAME));
    }

    public function dirnameProvider(): array
    {
        return [
            // Regression: the last character before the slash must be kept.
            ['1706/alpha.jpg', '1706'],
            ['a/b/c.png', 'a/b'],
        ];
    }

    public function testBasenameUnicode(): void
    {
        $this->assertSame('éfile.png', Helpers::basename('/some/path/éfile.png'));
        $this->assertSame('b', Helpers::basename('a/b/'));
    }

    public function testEscapeshellarg(): void
    {
        $this->assertSame("'abc'", Helpers::escapeshellarg('abc'));
        $this->assertSame("'a'\\''b'", Helpers::escapeshellarg("a'b"));
    }
}
