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

    private function remoteDocument(string $url = 'https://images.unsplash.com/photo-1?ixid=abc&w=1080'): Document
    {
        $document = new Document();
        $document->setTitle('Stock photo');
        $document->setSourceUrl($url);
        $document->setAttributionName('Jane Doe');

        return $document;
    }

    public function testPublicUrlIsTheProvidersAddress(): void
    {
        self::assertSame(
            'https://images.unsplash.com/photo-1?ixid=abc&w=1080',
            $this->generator()->publicUrl($this->remoteDocument()),
        );
    }

    /**
     * A remote document has no local route to fall back on, so the absolute
     * flavour - used by feeds, emails and JSON-LD - must not come back null.
     */
    public function testAbsoluteUrlIsAlsoTheProvidersAddress(): void
    {
        self::assertSame(
            'https://images.unsplash.com/photo-1?ixid=abc&w=1080',
            $this->generator()->publicUrlAbsolute($this->remoteDocument()),
        );
    }

    /**
     * The point of the exercise: a responsive layout asking for "medium" gets
     * an 800px picture from the CDN instead of the 4000px original.
     */
    public function testVariantsAreAskedOfTheCdnByWidth(): void
    {
        $url = $this->generator()->variantUrl($this->remoteDocument(), 'medium');

        self::assertIsString($url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('800', $query['w']);
        self::assertSame('webp', $query['fm']);
        self::assertSame('80', $query['q']);
    }

    /**
     * Unsplash attributes a view through the `ixid` it signs its URLs with;
     * rebuilding the address without it would break their tracking.
     */
    public function testVariantsKeepTheProvidersOwnParameters(): void
    {
        $url = (string) $this->generator()->variantUrl($this->remoteDocument(), 'thumbnail');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('abc', $query['ixid']);
        self::assertSame('256', $query['w']);
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
