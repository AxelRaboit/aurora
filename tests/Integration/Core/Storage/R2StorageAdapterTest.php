<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Core\Storage;

use Aurora\Core\Storage\Adapter\R2StorageAdapter;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\Exception\StorageException;
use Aurora\Core\Storage\R2\EnvR2ConfigurationProvider;
use Aurora\Core\Storage\R2\S3ClientFactory;
use Aurora\Core\Storage\Workspace\LocalWorkspace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function sprintf;

/**
 * The adapter against a real Cloudflare bucket.
 *
 * Skipped, loudly but harmlessly, on any machine without credentials, which
 * includes CI. That is the trade this file accepts: the suite has to stay
 * green for someone who has never heard of R2, and the only way to learn what
 * R2 actually does is to ask it. Everything checkable without a network lives
 * in the unit tests next door.
 *
 * Run it with `make test-r2`, which is also the only sane way to run it:
 * Symfony ignores `.env.local` when `APP_ENV=test`, on purpose, so that the
 * suite gives everyone the same result. The target puts the credentials in the
 * environment itself. Running phpunit directly skips these tests even on a
 * machine that has the keys, which looks like a broken suite and is not.
 *
 * Every object is written under `_test/`, with a random name, and removed in
 * `tearDown`. The bucket is a real one belonging to a real installation, so
 * nothing here touches a key it did not create.
 */
#[Group('r2')]
final class R2StorageAdapterTest extends TestCase
{
    private const string PREFIX = '_test';

    private R2StorageAdapter $adapter;
    private LocalWorkspace $workspace;
    /** @var list<string> */
    private array $written = [];
    /** @var list<string> */
    private array $localFiles = [];

    protected function setUp(): void
    {
        foreach (['R2_ENDPOINT', 'R2_BUCKET', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY'] as $variable) {
            if ('' === (string) ($_SERVER[$variable] ?? $_ENV[$variable] ?? '')) {
                self::markTestSkipped(sprintf('%s is not set: this suite needs a real R2 bucket. Run `make test-r2`.', $variable));
            }
        }

        $filesystem = new Filesystem();
        $provider = new EnvR2ConfigurationProvider(
            (string) $_SERVER['R2_ENDPOINT'],
            (string) $_SERVER['R2_BUCKET'],
            (string) $_SERVER['R2_ACCESS_KEY_ID'],
            (string) $_SERVER['R2_SECRET_ACCESS_KEY'],
            null,
        );

        $this->adapter = new R2StorageAdapter(new S3ClientFactory($provider), $provider, $filesystem);
        $this->workspace = new LocalWorkspace($filesystem);
    }

    protected function tearDown(): void
    {
        if ([] !== $this->written) {
            $this->adapter->deleteMany($this->written);
        }

        (new Filesystem())->remove($this->localFiles);
        $this->written = [];
        $this->localFiles = [];
    }

    public function testItAnswersForTheR2Disk(): void
    {
        self::assertSame(StorageDiskEnum::R2, $this->adapter->disk());
    }

    public function testItWritesAndReadsBackTheSameBytes(): void
    {
        $key = $this->key('txt');
        $contents = 'aurora '.bin2hex(random_bytes(24));

        $this->adapter->write($key, $contents);

        self::assertSame($contents, $this->adapter->read($key));
    }

    /**
     * The reason `stat()` does not use a HEAD.
     *
     * Cloudflare gzips compressible types as it serves them, and a gzipped
     * response has no `Content-Length`. A `text/plain` object therefore
     * reports no size over HEAD while a PNG reports its own, so metadata comes
     * from a listing, which reports what the object weighs in the bucket.
     */
    public function testSizeIsRightForACompressibleTypeCloudflareGzips(): void
    {
        $key = $this->key('txt');
        $contents = str_repeat('compressible ', 64);

        $this->adapter->write($key, $contents);

        self::assertSame(mb_strlen($contents, '8bit'), $this->adapter->stat($key)?->size);
    }

    public function testBinaryBytesSurviveTheRoundTripThroughLocalFiles(): void
    {
        $source = $this->pngFile(40, 25);
        $key = $this->key('png');

        $this->adapter->writeFromLocalFile($key, $source);

        self::assertFileExists($source, 'the source is left in place');
        self::assertSame(filesize($source), $this->adapter->stat($key)?->size);
        self::assertSame(file_get_contents($source), $this->adapter->read($key));
    }

    /**
     * The whole point of the workspace, proven against the backend that has no
     * paths: image and PDF tooling keeps receiving filenames.
     */
    public function testToolingThatNeedsAFilenameWorksAgainstTheBucket(): void
    {
        $key = $this->key('png');
        $this->adapter->writeFromLocalFile($key, $this->pngFile(40, 25));

        $dimensions = $this->workspace->readable(
            $this->adapter,
            $key,
            static fn (string $path): array|false => getimagesize($path),
        );

        self::assertNotFalse($dimensions);
        self::assertSame([40, 25], [$dimensions[0], $dimensions[1]]);
    }

    public function testAWorkspaceTargetStoresWhatTheWorkProduced(): void
    {
        $key = $this->key('png');
        $this->remember($key);

        $this->workspace->target($this->adapter, $key, static function (string $output): void {
            $image = imagecreatetruecolor(10, 10);
            imagepng($image, $output);
            imagedestroy($image);
        });

        self::assertTrue($this->adapter->exists($key));
    }

    public function testAWorkspaceTargetStoresNothingWhenTheWorkProducedNothing(): void
    {
        $key = $this->key('png');

        $this->workspace->target($this->adapter, $key, static function (string $output): void {
            // a PDF with no renderable page, a crop that could not happen
        });

        self::assertFalse($this->adapter->exists($key));
    }

    public function testAPresignedUrlServesTheBytesAndThenStopsWorking(): void
    {
        $key = $this->key('png');
        $source = $this->pngFile(20, 20);
        $this->adapter->writeFromLocalFile($key, $source);

        $url = $this->adapter->temporaryUrl($key, 120);
        self::assertSame(file_get_contents($source), file_get_contents($url));

        $expiring = $this->adapter->temporaryUrl($key, 1);
        sleep(2);
        self::assertFalse(@file_get_contents($expiring), 'an expired link is refused');
    }

    public function testThereIsNoPublicUrlWithoutAPublicHostname(): void
    {
        self::assertNull($this->adapter->publicUrl($this->key('png')));
    }

    public function testListingCarriesSizesSoNoSecondRequestIsNeeded(): void
    {
        $key = $this->key('txt');
        $contents = 'douze octets et quelques';
        $this->adapter->write($key, $contents);

        foreach ($this->adapter->list(self::PREFIX) as $object) {
            if ($object->key === $key) {
                self::assertSame(mb_strlen($contents, '8bit'), $object->size);

                return;
            }
        }

        self::fail('the object never appeared in the listing');
    }

    public function testAbsenceIsAnswered(): void
    {
        $missing = self::PREFIX.'/never-written-'.bin2hex(random_bytes(6)).'.txt';

        self::assertFalse($this->adapter->exists($missing));
        self::assertNull($this->adapter->stat($missing));
    }

    public function testReadingSomethingThatIsNotThereThrows(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->read(self::PREFIX.'/never-written-'.bin2hex(random_bytes(6)).'.txt');
    }

    public function testDeletingIsIdempotentAndBatchesIgnoreWhatIsAbsent(): void
    {
        $key = $this->key('txt');
        $this->adapter->write($key, 'x');

        $this->adapter->delete($key);
        $this->adapter->delete($key);
        $this->adapter->deleteMany([$key, self::PREFIX.'/absent-'.bin2hex(random_bytes(4)).'.txt']);

        self::assertFalse($this->adapter->exists($key));
    }

    /**
     * A key that prefix-matches another must not be mistaken for it.
     */
    public function testStatMatchesTheExactKeyRatherThanThePrefix(): void
    {
        $key = $this->key('txt');
        $neighbour = $key.'.bak';

        $this->adapter->write($neighbour, 'the neighbour');
        $this->remember($neighbour);

        self::assertNull($this->adapter->stat($key), 'a neighbour sharing the prefix is not this object');
    }

    private function key(string $extension): string
    {
        $key = sprintf('%s/%s.%s', self::PREFIX, bin2hex(random_bytes(8)), $extension);
        $this->remember($key);

        return $key;
    }

    private function remember(string $key): void
    {
        $this->written[] = $key;
    }

    private function pngFile(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 200, 40, 40));

        $path = sprintf('%s/aurora_r2_test_%s.png', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        imagepng($image, $path);
        imagedestroy($image);

        $this->localFiles[] = $path;

        return $path;
    }
}
