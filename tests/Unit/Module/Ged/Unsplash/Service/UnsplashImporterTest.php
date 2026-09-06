<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Ged\Unsplash\Service;

use Aurora\Module\Ged\Document\Dto\DocumentInputFactory;
use Aurora\Module\Ged\Document\Dto\DocumentInputInterface;
use Aurora\Module\Ged\Document\Entity\Document;
use Aurora\Module\Ged\Document\Entity\DocumentInterface;
use Aurora\Module\Ged\Document\Manager\DocumentManagerInterface;
use Aurora\Module\Ged\DocumentCategory\Entity\DocumentCategory;
use Aurora\Module\Ged\DocumentCategory\Repository\DocumentCategoryRepository;
use Aurora\Module\Ged\DocumentCategory\Service\InlineUploadCategoryProvider;
use Aurora\Module\Ged\Unsplash\Service\UnsplashClient;
use Aurora\Module\Ged\Unsplash\Service\UnsplashImporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The import is where a URL chosen in a browser becomes a row every page on
 * the site will render. Everything worth testing here is a refusal.
 */
final class UnsplashImporterTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $captured = null;

    /** @param array<string, mixed> $photo */
    private function import(array $photo, MockHttpClient $http = new MockHttpClient()): DocumentInterface
    {
        $category = new DocumentCategory();
        $category->setName('Inline');
        // The id is normally the database's to give; the importer only reads it.
        $id = new ReflectionProperty($category, 'id');
        $id->setValue($category, 1);

        // The provider is `final readonly`, so it cannot be doubled. Built
        // without its constructor and given only the repository it reads:
        // `resolve()` finds the category and never reaches the two
        // collaborators that would have needed a database.
        $categoryRepository = $this->createStub(DocumentCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn($category);

        $categoryProvider = (new ReflectionClass(InlineUploadCategoryProvider::class))->newInstanceWithoutConstructor();
        $repositoryProperty = new ReflectionProperty($categoryProvider, 'documentCategoryRepository');
        $repositoryProperty->setValue($categoryProvider, $categoryRepository);

        $manager = $this->createStub(DocumentManagerInterface::class);
        $manager->method('create')->willReturnCallback(function (DocumentInputInterface $input): DocumentInterface {
            $this->captured = [
                'title' => $input->getTitle(),
                'sourceUrl' => $input->getSourceUrl(),
                'attributionName' => $input->getAttributionName(),
                'attributionUrl' => $input->getAttributionUrl(),
                'mimeType' => $input->getMimeType(),
                'alt' => $input->getAlt(),
            ];

            return new Document();
        });

        $importer = new UnsplashImporter(
            $manager,
            new DocumentInputFactory(),
            $categoryProvider,
            new UnsplashClient($http, new NullLogger(), 'key'),
        );

        return $importer->import($photo);
    }

    /** @return array<string, mixed> */
    private function photo(array $overrides = []): array
    {
        return [
            'url' => 'https://images.unsplash.com/photo-1?ixid=abc',
            'authorName' => 'Jane Doe',
            'authorUrl' => 'https://unsplash.com/@jane',
            'description' => 'A tidy desk',
            'downloadLocation' => 'https://api.unsplash.com/photos/abc/download',
            'width' => 4000,
            'height' => 3000,
            ...$overrides,
        ];
    }

    /**
     * The URL arrives from the browser, and a browser can be told to send
     * anything. Without the host check, "import" would store an arbitrary
     * address and every page would render it.
     */
    public function testAnAddressOutsideUnsplashIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->import($this->photo(['url' => 'https://evil.example.com/tracker.gif']));
    }

    /** Attribution is a condition of use, so a photo without one cannot be filed. */
    public function testAPhotoWithoutAPhotographerIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->import($this->photo(['authorName' => '  ']));
    }

    public function testTheCreditIsStoredWithUtmParametersTheGuidelinesRequire(): void
    {
        $this->import($this->photo());

        self::assertSame('Jane Doe', $this->captured['attributionName']);
        self::assertSame(
            'https://unsplash.com/@jane?utm_source=aurora&utm_medium=referral',
            $this->captured['attributionUrl'],
        );
    }

    public function testTheDocumentCarriesTheRemoteAddressAndNoFile(): void
    {
        $this->import($this->photo());

        self::assertSame('https://images.unsplash.com/photo-1?ixid=abc', $this->captured['sourceUrl']);
        self::assertSame('A tidy desk', $this->captured['title']);
        self::assertSame('image/webp', $this->captured['mimeType']);
    }

    /** A photo Unsplash left undescribed still needs a name in the library. */
    public function testAnUndescribedPhotoIsTitledAfterItsAuthor(): void
    {
        $this->import($this->photo(['description' => null]));

        self::assertSame('Unsplash - Jane Doe', $this->captured['title']);
    }

    /** Their terms ask to be told when a photo is taken up. */
    public function testTheProviderIsToldThePhotoWasTaken(): void
    {
        $pinged = [];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$pinged): MockResponse {
            $pinged[] = $url;

            return new MockResponse('{}');
        });

        $this->import($this->photo(), $http);

        self::assertSame(['https://api.unsplash.com/photos/abc/download'], $pinged);
    }
}
