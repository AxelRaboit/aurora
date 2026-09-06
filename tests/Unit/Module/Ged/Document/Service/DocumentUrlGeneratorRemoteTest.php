<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Ged\Document\Service;

use Aurora\Module\Ged\Document\Entity\Document;
use Aurora\Module\Ged\Document\Service\DocumentUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Every picture on the public site is addressed through this class, so a
 * document whose bytes are not ours has to come out of it looking like any
 * other - otherwise each of the twenty-odd call sites would need to learn the
 * difference.
 */
final class DocumentUrlGeneratorRemoteTest extends TestCase
{
    private const string SOURCE = 'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg';

    private function generator(): DocumentUrlGenerator
    {
        // A stub, not a mock: the routing is scenery here, and only the local
        // case ever calls it.
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => '/uploads/'.($params['path'] ?? ''),
        );

        return new DocumentUrlGenerator($urlGenerator);
    }

    private function remoteDocument(string $url = self::SOURCE): Document
    {
        $document = new Document();
        $document->setTitle('Stock photo');
        $document->setSourceUrl($url);
        $document->setAttributionName('Jane Doe');

        return $document;
    }

    /** @return array<string, string> */
    private function queryOf(?string $url): array
    {
        self::assertIsString($url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        /* @var array<string, string> $query */
        return $query;
    }

    /**
     * The bare address is the photographer's full-size original, which can be
     * twenty-five megapixels. Anything that draws the picture asks the CDN for
     * a sane width instead; `getSourceUrl()` is where the original still lives.
     */
    public function testPublicUrlIsTheProvidersAddressAtAWorkableWidth(): void
    {
        $url = $this->generator()->publicUrl($this->remoteDocument());

        self::assertStringStartsWith(self::SOURCE.'?', (string) $url);
        self::assertSame('1920', $this->queryOf($url)['w']);
    }

    /**
     * A remote document has no local route to fall back on, so the absolute
     * flavour - used by feeds, emails and JSON-LD - must not come back null.
     */
    public function testAbsoluteUrlIsAlsoTheProvidersAddress(): void
    {
        $url = $this->generator()->publicUrlAbsolute($this->remoteDocument());

        self::assertStringStartsWith('https://images.pexels.com/', (string) $url);
        self::assertSame('1920', $this->queryOf($url)['w']);
    }

    /**
     * The point of the exercise: a responsive layout asking for "medium" gets
     * an 800px picture from the CDN instead of the 4000px original.
     */
    public function testVariantsAreAskedOfTheCdnByWidth(): void
    {
        $query = $this->queryOf($this->generator()->variantUrl($this->remoteDocument(), 'medium'));

        self::assertSame('800', $query['w']);
        self::assertSame('compress', $query['auto']);
        self::assertSame('tinysrgb', $query['cs']);
    }

    /**
     * Pexels' own renditions are cropped to a fixed box by a height and a
     * pixel ratio on the URL. Left in place, asking for a narrower width
     * would crop the picture rather than shrink it.
     */
    public function testTheProvidersCroppingParametersAreDropped(): void
    {
        $document = $this->remoteDocument(self::SOURCE.'?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940');

        $query = $this->queryOf($this->generator()->variantUrl($document, 'thumbnail'));

        self::assertSame('256', $query['w']);
        self::assertArrayNotHasKey('h', $query);
        self::assertArrayNotHasKey('dpr', $query);
    }

    /**
     * A provider that signs or tags its addresses has a reason to, so only
     * the parameters we are deliberately overriding are touched.
     */
    public function testAnyOtherParameterTheProviderPutThereIsKept(): void
    {
        $document = $this->remoteDocument(self::SOURCE.'?ixid=abc');

        self::assertSame('abc', $this->queryOf($this->generator()->variantUrl($document, 'large'))['ixid']);
    }

    public function testAnUnknownVariantNameYieldsNullAsItDoesForLocalFiles(): void
    {
        self::assertNull($this->generator()->variantUrl($this->remoteDocument(), 'gigantic'));
    }

    public function testLocalDocumentsAreUntouched(): void
    {
        $document = new Document();
        $document->setTitle('Ours');
        $document->setFilePath('ged/2026/09/photo.webp');
        $document->setVariants(['medium' => 'ged/2026/09/variants/medium/photo.webp']);

        $generator = $this->generator();

        self::assertSame('/uploads/ged/2026/09/photo.webp', $generator->publicUrl($document));
        self::assertSame('/uploads/ged/2026/09/variants/medium/photo.webp', $generator->variantUrl($document, 'medium'));
    }
}
