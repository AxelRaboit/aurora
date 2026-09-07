<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\PostType;

use Aurora\Module\Editorial\Post\Dto\PostInputFactoryInterface;
use Aurora\Module\Editorial\Post\Manager\PostManagerInterface;
use Aurora\Module\Editorial\Post\View\PageViewBuilder;
use Aurora\Module\Editorial\PostType\Entity\PostType;
use Aurora\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What a listing page calls itself, and whose header it borrows.
 *
 * Both are answers to the same complaint: the page printed the label, so it
 * announced itself in the singular, and it had no way to look like the rest of
 * the site. The title is a fact about the type; the header is borrowed from a
 * publication rather than reinvented here, so the page gets the whole banner -
 * its settings and its words in each language - instead of a poorer copy.
 *
 * The refusals matter as much as the happy path: a draft is not something a
 * listing page may put on the public site, and neither is a publication that
 * has nothing to say in the language being read.
 */
final class ArchiveHeaderTest extends IntegrationTestCase
{
    private PageViewBuilder $pageViewBuilder;

    private PostManagerInterface $postManager;

    private PostInputFactoryInterface $inputFactory;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $container = static::getContainer();
        $this->pageViewBuilder = $container->get(PageViewBuilder::class);
        $this->postManager = $container->get(PostManagerInterface::class);
        $this->inputFactory = $container->get(PostInputFactoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public function testTheListingPageIsNamedByItsOwnTitle(): void
    {
        $postType = $this->postType();
        $postType->setArchiveTitle('Services');

        self::assertSame('Services', $this->view($postType)['postType']['heading']);
    }

    /** Every type predates the field, and "Service" beats a blank heading. */
    public function testItFallsBackToTheLabelWhenNoTitleWasWritten(): void
    {
        self::assertSame('Service', $this->view($this->postType())['postType']['heading']);
    }

    public function testItBorrowsTheWholeBannerOfThePublicationItNames(): void
    {
        $postType = $this->postType();
        $postType->setArchivePostId($this->headerPostId('published'));
        $this->entityManager->flush();

        $banner = $this->view($postType)['postType']['banner'];

        self::assertIsArray($banner);
        // `full_aligned` is what a full-width banner is since `full` was
        // retired: background across the viewport, text in line with the page.
        // Asserted rather than assumed, because it is the value the archive
        // template reads to decide whether to render above <main>.
        self::assertSame('full_aligned', $banner['width'], 'the borrowed banner keeps its own settings');
        self::assertStringContainsString('Nos services', json_encode($banner, JSON_THROW_ON_ERROR));
    }

    /**
     * The summary travels with the header, and for the same reason: a listing
     * page carrying someone else's banner and the site's default description
     * describes itself by accident.
     */
    public function testItBorrowsTheSummaryTooSoTheSearchResultIsAboutThisPage(): void
    {
        $postType = $this->postType();
        $postType->setArchivePostId($this->headerPostId('published'));
        $this->entityManager->flush();

        self::assertSame('Ce que je propose, en détail.', $this->view($postType)['postType']['description']);
    }

    /** Nothing designated: the page keeps the site's own description. */
    public function testItHasNoSummaryToBorrowWhenNothingIsDesignated(): void
    {
        self::assertNull($this->view($this->postType())['postType']['description']);
    }

    /** A draft is not something a listing page may publish on its behalf. */
    public function testItRefusesToBorrowFromADraft(): void
    {
        $postType = $this->postType();
        $postType->setArchivePostId($this->headerPostId('draft'));
        $this->entityManager->flush();

        self::assertNull($this->view($postType)['postType']['banner']);
    }

    /** Nothing designated: the page keeps the plain header it always had. */
    public function testAPageWithNothingDesignatedHasNoHeaderToDraw(): void
    {
        self::assertNull($this->view($this->postType())['postType']['banner']);
    }

    /** @return array<string, mixed> */
    private function view(PostType $postType): array
    {
        return $this->pageViewBuilder->archiveView(
            'fr',
            $postType,
            ['items' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1],
        );
    }

    private function postType(): PostType
    {
        $postType = new PostType();
        $postType->setSlug('service-'.bin2hex(random_bytes(4)));
        $postType->setLabel('Service');
        $postType->setHasArchive(true);

        $this->entityManager->persist($postType);
        $this->entityManager->flush();

        return $postType;
    }

    private function headerPostId(string $status): int
    {
        $post = $this->postManager->create($this->inputFactory->fromArray([
            'postTypeId' => $this->postType()->getId(),
            'status' => $status,
            'bannerLayout' => [
                'enabled' => true,
                'width' => 'full_aligned',
                'height' => 'lg',
                'items' => [['id' => 'a1', 'type' => 'text', 'titleSize' => 'xl']],
            ],
            'translations' => [
                'fr' => [
                    'title' => 'Nos services',
                    'description' => 'Ce que je propose, en détail.',
                    'slug' => 'nos-services-'.bin2hex(random_bytes(4)),
                    'banner' => ['items' => ['a1' => ['title' => 'Nos services']]],
                ],
            ],
        ]));

        $this->entityManager->flush();

        return (int) $post->getId();
    }

    /** An id naming nothing is refused, exactly as a missing one is. */
    public function testItRefusesAnIdThatNamesNothing(): void
    {
        $postType = $this->postType();
        $postType->setArchivePostId(987654321);
        $this->entityManager->flush();

        self::assertNull($this->view($postType)['postType']['banner']);
    }
}
