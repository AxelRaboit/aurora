<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Storage;

use Aurora\Core\Storage\Adapter\LocalStorageAdapter;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\Exception\StorageException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The reference implementation of the storage contract.
 *
 * Every other adapter will be judged against what this one does, so the
 * behaviours worth nailing down are the ones a remote backend could plausibly
 * get wrong: that absence is answered rather than thrown, that deleting twice
 * is fine, that a listing carries sizes and dates, and that a key cannot walk
 * out of the storage root.
 */
final class LocalStorageAdapterTest extends TestCase
{
    private string $root;
    private Filesystem $filesystem;
    private LocalStorageAdapter $adapter;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir().'/aurora_storage_test_'.bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->root);
        $this->adapter = new LocalStorageAdapter($this->filesystem, $this->root);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);
    }

    public function testItAnswersForTheLocalDisk(): void
    {
        self::assertSame(StorageDiskEnum::Local, $this->adapter->disk());
    }

    public function testItWritesAndReadsBack(): void
    {
        $this->adapter->write('ged/2026/09/note.txt', 'bonjour');

        self::assertSame('bonjour', $this->adapter->read('ged/2026/09/note.txt'));
        self::assertFileExists($this->root.'/ged/2026/09/note.txt');
    }

    public function testItOverwritesAnExistingKey(): void
    {
        $this->adapter->write('a/b.txt', 'premier');
        $this->adapter->write('a/b.txt', 'second');

        self::assertSame('second', $this->adapter->read('a/b.txt'));
    }

    public function testExistsAnswersRatherThanThrows(): void
    {
        self::assertFalse($this->adapter->exists('nowhere/at/all.txt'));

        $this->adapter->write('somewhere.txt', 'x');

        self::assertTrue($this->adapter->exists('somewhere.txt'));
    }

    public function testReadingAMissingKeyThrows(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->read('nowhere/at/all.txt');
    }

    public function testDeleteIsIdempotent(): void
    {
        $this->adapter->write('gone.txt', 'x');

        $this->adapter->delete('gone.txt');
        $this->adapter->delete('gone.txt');

        self::assertFalse($this->adapter->exists('gone.txt'));
    }

    public function testDeleteManyRemovesWhatIsThereAndIgnoresWhatIsNot(): void
    {
        $this->adapter->write('one.txt', 'x');
        $this->adapter->write('two.txt', 'x');

        $this->adapter->deleteMany(['one.txt', 'two.txt', 'three.txt']);

        self::assertFalse($this->adapter->exists('one.txt'));
        self::assertFalse($this->adapter->exists('two.txt'));
    }

    public function testDeleteManyOnAnEmptyListDoesNothing(): void
    {
        $this->adapter->write('kept.txt', 'x');

        $this->adapter->deleteMany([]);

        self::assertTrue($this->adapter->exists('kept.txt'));
    }

    /**
     * The one that decides whether a prune command costs one request or one
     * per file once a remote backend exists.
     */
    public function testListingCarriesSizeAndDateSoCallersNeedNoSecondRequest(): void
    {
        $this->adapter->write('ged/2026/09/a.txt', 'douze octets');

        $objects = iterator_to_array($this->adapter->list('ged'));

        self::assertCount(1, $objects);
        self::assertSame('ged/2026/09/a.txt', $objects[0]->key);
        self::assertSame(12, $objects[0]->size);
        self::assertGreaterThan(0, $objects[0]->lastModifiedAt->getTimestamp());
    }

    public function testListingIsScopedToItsPrefix(): void
    {
        $this->adapter->write('ged/in.txt', 'x');
        $this->adapter->write('users/out.txt', 'x');

        $keys = array_map(
            static fn ($object): string => $object->key,
            iterator_to_array($this->adapter->list('ged')),
        );

        self::assertSame(['ged/in.txt'], $keys);
    }

    public function testListingAMissingPrefixYieldsNothing(): void
    {
        self::assertSame([], iterator_to_array($this->adapter->list('never/written')));
    }

    public function testStatDescribesAnObjectAndAnswersNullForAMissingOne(): void
    {
        $this->adapter->write('ged/a.txt', 'quatre');

        $object = $this->adapter->stat('ged/a.txt');

        self::assertNotNull($object);
        self::assertSame('ged/a.txt', $object->key);
        self::assertSame(6, $object->size);
        self::assertNull($this->adapter->stat('ged/missing.txt'));
    }

    public function testItCopiesAStoredObjectOutToALocalFile(): void
    {
        $this->adapter->write('ged/a.txt', 'contenu');
        $target = $this->root.'/../aurora_storage_out_'.bin2hex(random_bytes(4)).'.txt';

        $this->adapter->copyToLocalFile('ged/a.txt', $target);

        self::assertSame('contenu', file_get_contents($target));
        $this->filesystem->remove($target);
    }

    public function testItWritesFromALocalFile(): void
    {
        $source = sys_get_temp_dir().'/aurora_storage_src_'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($source, 'depuis le disque');

        $this->adapter->writeFromLocalFile('ged/copied.txt', $source);

        self::assertSame('depuis le disque', $this->adapter->read('ged/copied.txt'));
        self::assertFileExists($source, 'the source is left alone');
        $this->filesystem->remove($source);
    }

    /**
     * LocalWorkspace hands callers the stored file itself, so a caller that
     * writes and then asks for it to be stored passes the same path twice.
     * Naive copy() truncates the destination before reading the source, which
     * on the same inode means losing the file.
     */
    public function testWritingAFileOntoItselfKeepsIt(): void
    {
        $this->adapter->write('ged/same.txt', 'intact');

        $this->adapter->writeFromLocalFile('ged/same.txt', $this->root.'/ged/same.txt');

        self::assertSame('intact', $this->adapter->read('ged/same.txt'));
    }

    public function testWritingFromAMissingSourceThrows(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->writeFromLocalFile('ged/a.txt', $this->root.'/not-here.txt');
    }

    public function testAKeyCannotClimbOutOfTheRoot(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->write('../escaped.txt', 'x');
    }

    public function testAKeyCannotClimbOutOfTheRootThroughASubdirectory(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->read('ged/../../escaped.txt');
    }

    public function testLocalPathPointsInsideTheRootEvenBeforeTheFileExists(): void
    {
        $path = $this->adapter->localPath('ged/2026/09/not-yet.txt');

        self::assertStringStartsWith($this->root, $path);
        self::assertStringEndsWith('ged/2026/09/not-yet.txt', $path);
    }
}
