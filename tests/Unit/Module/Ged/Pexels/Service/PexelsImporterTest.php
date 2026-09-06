<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Ged\Pexels\Service;

use Aurora\Module\Ged\Document\Dto\DocumentInputFactory;
use Aurora\Module\Ged\Document\Dto\DocumentInputInterface;
use Aurora\Module\Ged\Document\Entity\Document;
use Aurora\Module\Ged\Document\Entity\DocumentInterface;
use Aurora\Module\Ged\Document\Manager\DocumentManagerInterface;
use Aurora\Module\Ged\DocumentCategory\Entity\DocumentCategory;
use Aurora\Module\Ged\DocumentCategory\Repository\DocumentCategoryRepository;
use Aurora\Module\Ged\DocumentCategory\Service\InlineUploadCategoryProvider;
use Aurora\Module\Ged\Pexels\Service\PexelsImporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * The import is where a URL chosen in a browser becomes a row every page on
 * the site will render. Everything worth testing here is a refusal.
 */
final class PexelsImporterTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $captured = null;

    /** @param array<string, mixed> $photo */
    private function import(array $photo): DocumentInterface
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

        $importer = new PexelsImporter($manager, new DocumentInputFactory(), $categoryProvider);

        return $importer->import($photo);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function photo(array $overrides = []): array
    {
        return [
            'url' => 'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg',
            'authorName' => 'Jane Doe',
            'authorUrl' => 'https://www.pexels.com/@jane',
            'description' => 'A tidy desk',
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
    public function testAnAddressOutsidePexelsIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->import($this->photo(['url' => 'https://evil.example.com/tracker.gif']));
    }

    /** A lookalike host is the whole reason the check reads the host and not the string. */
    public function testALookalikeHostIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->import($this->photo(['url' => 'https://images.pexels.com.evil.example.com/photo.jpeg']));
    }

    /** Attribution is a condition of use, so a photo without one cannot be filed. */
    public function testAPhotoWithoutAPhotographerIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->import($this->photo(['authorName' => '  ']));
    }

    public function testTheCreditTravelsWithTheDocument(): void
    {
        $this->import($this->photo());

        self::assertSame('Jane Doe', $this->captured['attributionName']);
        self::assertSame('https://www.pexels.com/@jane', $this->captured['attributionUrl']);
    }

    public function testTheDocumentCarriesTheRemoteAddressAndNoFile(): void
    {
        $this->import($this->photo());

        self::assertSame(
            'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg',
            $this->captured['sourceUrl'],
        );
        self::assertSame('A tidy desk', $this->captured['title']);
        // What MimeGroupEnum reads to decide this is an image at all.
        self::assertSame('image/jpeg', $this->captured['mimeType']);
    }

    /** A photo Pexels left undescribed still needs a name in the library. */
    public function testAnUndescribedPhotoIsTitledAfterItsAuthor(): void
    {
        $this->import($this->photo(['description' => null]));

        self::assertSame('Pexels - Jane Doe', $this->captured['title']);
    }
}
