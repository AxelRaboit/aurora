<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Storage;

use Aurora\Core\Storage\ActiveStorageDiskProviderInterface;
use Aurora\Core\Storage\Adapter\LocalStorageAdapter;
use Aurora\Core\Storage\Adapter\StorageAdapterInterface;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\StorageManager;
use Aurora\Core\Storage\StoredFileLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Which backend answers for a `/uploads/{path}` request.
 *
 * The case that matters is the one that was missing: a document moved to a
 * remote backend while new files still go to the server's disk. That
 * combination is offered by the product on purpose - the relocation button
 * exists for it - and it used to serve 404 for every moved file, because the
 * locator stopped looking once it saw the active disk was the local one.
 *
 * Found in production on 12/09/2026, on four films and their posters that had
 * been moved deliberately and vanished from a public page.
 */
final class StoredFileLocatorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/aurora-locator-'.uniqid();
        mkdir($this->workDir.'/local', 0o777, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->workDir);
    }

    public function testAFilePresentLocallyIsServedLocally(): void
    {
        $local = $this->local();
        $local->write('photo.jpg', 'bytes');

        $locator = $this->locator([$local, $this->remote()], StorageDiskEnum::Local);

        self::assertSame($local, $locator->locate('photo.jpg'));
    }

    /**
     * The regression. New files go to the server, one document was moved to
     * the bucket, and its address has to keep working.
     */
    public function testAFileMovedToTheRemoteBackendIsFoundWhileTheActiveDiskIsLocal(): void
    {
        $remote = $this->remote();
        $remote->objects['reel.mp4'] = 'bytes';

        $locator = $this->locator([$this->local(), $remote], StorageDiskEnum::Local);

        self::assertSame($remote, $locator->locate('reel.mp4'));
    }

    public function testAFileNobodyHoldsIsFoundNowhere(): void
    {
        $locator = $this->locator([$this->local(), $this->remote()], StorageDiskEnum::Local);

        self::assertNull($locator->locate('gone.jpg'));
    }

    /**
     * The local check is a syscall; the remote one is a billed request. A file
     * sitting on the server must not cost one, which is the reason the local
     * disk is asked first rather than last.
     */
    public function testALocalHitAsksTheRemoteBackendNothing(): void
    {
        $local = $this->local();
        $local->write('photo.jpg', 'bytes');
        $remote = $this->remote();

        $this->locator([$local, $remote], StorageDiskEnum::Local)->locate('photo.jpg');

        self::assertSame([], $remote->existsCalls);
    }

    /**
     * A backend nobody configured has no address to ask and throws when
     * called. Sweeping the adapters must step over it rather than catch it,
     * because catching would hide the failures of a backend that *is*
     * configured.
     */
    public function testAnUnconfiguredBackendIsNotAsked(): void
    {
        $remote = $this->remote();
        $remote->ready = false;
        $remote->objects['reel.mp4'] = 'bytes';

        $locator = $this->locator([$this->local(), $remote], StorageDiskEnum::Local);

        self::assertNull($locator->locate('reel.mp4'));
        self::assertSame([], $remote->existsCalls);
    }

    private function local(): LocalStorageAdapter
    {
        return new LocalStorageAdapter(new Filesystem(), $this->workDir.'/local');
    }

    private function remote(): InMemoryStorageAdapter
    {
        return new InMemoryStorageAdapter(StorageDiskEnum::R2);
    }

    /** @param list<StorageAdapterInterface> $adapters */
    private function locator(array $adapters, StorageDiskEnum $active): StoredFileLocator
    {
        $provider = new class($active) implements ActiveStorageDiskProviderInterface {
            public function __construct(private readonly StorageDiskEnum $disk) {}

            public function activeDisk(): StorageDiskEnum
            {
                return $this->disk;
            }
        };

        return new StoredFileLocator(new StorageManager($adapters, $provider));
    }
}
