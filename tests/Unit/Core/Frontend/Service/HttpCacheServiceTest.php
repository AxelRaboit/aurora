<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Frontend\Service;

use Aurora\Core\Frontend\Service\AssetBuildStamp;
use Aurora\Core\Frontend\Service\HttpCacheService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page is fresh only if neither half of it has moved: not its content, and
 * not the assets it names. Getting that wrong is what served visitors an
 * unstyled page after every deploy.
 */
final class HttpCacheServiceTest extends TestCase
{
    private const string CONTENT_CHANGED = '2026-09-06 09:36:06';

    private function service(?string $builtAt): HttpCacheService
    {
        // `final` and reading the filesystem in its constructor's shadow, so
        // it is built empty and told what it would have found.
        $stamp = new AssetBuildStamp('/nowhere');

        $resolved = new ReflectionProperty($stamp, 'resolved');
        $resolved->setValue($stamp, true);

        if (null !== $builtAt) {
            $property = new ReflectionProperty($stamp, 'builtAt');
            $property->setValue($stamp, new DateTimeImmutable($builtAt));
        }

        return new HttpCacheService($stamp);
    }

    private function requestSince(string $date): Request
    {
        return new Request(server: [
            'HTTP_IF_MODIFIED_SINCE' => (new DateTimeImmutable($date))->format('D, d M Y H:i:s').' GMT',
        ]);
    }

    /** The ordinary case: nothing moved, so the copy the visitor holds is good. */
    public function testAnUnchangedPageIsStillFresh(): void
    {
        $response = $this->service('2026-09-06 08:00:00')->checkNotModified(
            $this->requestSince(self::CONTENT_CHANGED),
            new DateTimeImmutable(self::CONTENT_CHANGED),
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    /**
     * The bug this exists for. The article has not been touched in months,
     * but the deploy renamed every stylesheet it names - so the copy the
     * visitor holds is worthless, and answering 304 hands them a page with
     * no styling until they think to hard-refresh.
     */
    public function testADeployMakesAnUntouchedPageStale(): void
    {
        $response = $this->service('2026-09-06 15:02:00')->checkNotModified(
            $this->requestSince(self::CONTENT_CHANGED),
            new DateTimeImmutable(self::CONTENT_CHANGED),
        );

        self::assertNull($response, 'A page must be re-rendered after its assets were rebuilt.');
    }

    /** A build older than the content decides nothing; the content still does. */
    public function testAnOlderBuildDoesNotOverrideTheContentDate(): void
    {
        $response = new Response();
        $this->service('2026-01-01 00:00:00')->setPublicCache($response, new DateTimeImmutable(self::CONTENT_CHANGED));

        self::assertEquals(
            new DateTimeImmutable(self::CONTENT_CHANGED),
            $response->getLastModified(),
        );
    }

    /** A dev environment serving from the Vite server has no manifest to read. */
    public function testWithoutABuiltManifestTheContentDateIsUsedAlone(): void
    {
        $response = new Response();
        $this->service(null)->setPublicCache($response, new DateTimeImmutable(self::CONTENT_CHANGED));

        self::assertEquals(
            new DateTimeImmutable(self::CONTENT_CHANGED),
            $response->getLastModified(),
        );
    }

    /** No content date, no validator - a page nobody can date is never conditional. */
    public function testAPageWithoutADateIsNeverJudgedFresh(): void
    {
        self::assertNull(
            $this->service('2026-09-06 15:02:00')->checkNotModified(new Request(), null),
        );
    }

    /**
     * The three below predate the asset stamp and are kept as they were: what
     * `setPublicCache` and `setSharedCache` put on a response is unchanged,
     * and a regression there would be invisible from the tests above.
     */
    public function testSetPublicCacheAppliesHeaders(): void
    {
        $response = new Response();

        $this->service(null)->setPublicCache($response, new DateTimeImmutable(self::CONTENT_CHANGED), 600);

        self::assertNotNull($response->getLastModified());
        self::assertSame(600, $response->getMaxAge());
    }

    public function testSetPublicCacheWithNullLastModifiedSkipsHeader(): void
    {
        $response = new Response();

        $this->service(null)->setPublicCache($response, null, 300);

        self::assertNull($response->getLastModified());
        self::assertSame(300, $response->getMaxAge());
    }

    public function testSetSharedCacheSetsSharedMaxAge(): void
    {
        $response = new Response();

        $this->service(null)->setSharedCache($response, 120);

        self::assertTrue($response->headers->has('Cache-Control'));
    }
}
