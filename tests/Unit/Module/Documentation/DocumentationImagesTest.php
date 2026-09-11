<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Documentation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_diff;
use function dirname;
use function implode;
use function preg_match_all;
use function sprintf;
use function str_replace;

/**
 * Every screenshot a page points at exists, and every screenshot that ships
 * is pointed at.
 *
 * Both directions, because both have happened. A page whose picture is
 * missing draws an empty frame, which reads as a broken product; a picture
 * nobody references is dead weight in a package that clients download, and
 * it is how a folder of twenty-two megabytes quietly becomes forty.
 *
 * The pictures themselves are checked nowhere else: nothing renders them at
 * build time, so a rename that misses one file would only be found by
 * somebody opening that page.
 */
final class DocumentationImagesTest extends TestCase
{
    public function testEveryPictureAPageAsksForExists(): void
    {
        $missing = [];

        foreach (self::references() as $reference => $pages) {
            if (!is_file(self::imagesDir().'/'.$reference)) {
                $missing[] = sprintf('%s (%s)', $reference, implode(', ', $pages));
            }
        }

        self::assertSame([], $missing, "Ces captures sont citées et absentes :\n");
    }

    public function testEveryPictureShippedIsUsed(): void
    {
        $onDisk = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::imagesDir()));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && 'png' === $file->getExtension()) {
                $onDisk[] = str_replace(self::imagesDir().'/', '', $file->getPathname());
            }
        }

        $orphans = array_values(array_diff($onDisk, array_keys(self::references())));
        sort($orphans);

        self::assertSame([], $orphans, "Ces captures ne sont citées par aucune page :\n");
    }

    /**
     * Which picture each page asks for, keyed by its path under images/.
     *
     * @return array<string, list<string>>
     */
    private static function references(): array
    {
        $found = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::contentDir()));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || 'md' !== $file->getExtension()) {
                continue;
            }

            $matches = [];
            preg_match_all('#\]\(\.\./\.\./images/([\w-]+/[\w.-]+\.png)\)#', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $reference) {
                $found[$reference][] = $file->getBasename();
            }
        }

        return $found;
    }

    private static function contentDir(): string
    {
        return dirname(__DIR__, 4).'/src/Module/Documentation/content';
    }

    private static function imagesDir(): string
    {
        return dirname(__DIR__, 4).'/src/Module/Documentation/images';
    }
}
