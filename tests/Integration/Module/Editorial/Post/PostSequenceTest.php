<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\Post;

use Aurora\Module\Editorial\Post\Entity\Post;
use Aurora\Module\Editorial\Post\Enum\PostStatusEnum;
use Aurora\Module\Editorial\PostType\Entity\PostType;
use Aurora\Module\Editorial\Taxonomy\Entity\Taxonomy;
use Aurora\Module\Editorial\Taxonomy\Entity\TaxonomyTerm;
use Aurora\Tests\Integration\IntegrationTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function bin2hex;
use function random_bytes;
use function sprintf;

/**
 * The summary beside a page, and the way to the next one.
 *
 * What separates a documentation from a list of articles. It hangs off three
 * things that already existed and were never used together: a hierarchical
 * taxonomy for the tree, a reading position for the order, and the `sequence`
 * support on the type for the switch.
 *
 * The switch is the part worth testing hardest. Every other type of content on
 * a site renders through the same template, so a summary that leaked onto an
 * article would be the defect this feature introduces rather than fixes.
 */
final class PostSequenceTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private PostType $type;

    private TaxonomyTerm $rubric;

    /** @var list<array{class-string, int}> the summary clears the manager, so tidy-up refetches */
    private array $created = [];

    private string $suffix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->suffix = bin2hex(random_bytes(4));

        $this->type = new PostType();
        $this->type->setSlug('guide-'.$this->suffix)
            ->setLabel('Guide')
            ->setHasArchive(true)
            ->setSupports(['blocks', 'sequence']);

        $this->entityManager->persist($this->type);

        $taxonomy = new Taxonomy();
        $taxonomy->setSlug('partie-'.$this->suffix)->setHierarchical(true)->addPostType($this->type);
        $taxonomy->translate('fr')->setLabel('Parties');

        $this->entityManager->persist($taxonomy);

        $section = new TaxonomyTerm();
        $section->setTaxonomy($taxonomy)->setPosition(1);
        $section->translate('fr')->setName('Pour commencer')->setSlug('pour-commencer-'.$this->suffix);

        $this->rubric = new TaxonomyTerm();
        $this->rubric->setTaxonomy($taxonomy)->setParent($section)->setPosition(1);
        $this->rubric->translate('fr')->setName('Les bases')->setSlug('les-bases-'.$this->suffix);

        $this->entityManager->persist($section);
        $this->entityManager->persist($this->rubric);
        $this->entityManager->flush();

        $this->track($this->rubric);
        $this->track($section);
        $this->track($taxonomy);
        $this->track($this->type);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as [$class, $id]) {
            $entity = $this->entityManager->find($class, $id);

            if (null !== $entity) {
                $this->entityManager->remove($entity);
            }
        }

        $this->entityManager->flush();
        $this->created = [];

        parent::tearDown();
    }

    private function track(object $entity): void
    {
        $this->created[] = [$entity::class, (int) $entity->getId()];
    }

    public function testTheMiddlePageShowsBothNeighbours(): void
    {
        $this->page('Premier', 1);
        $this->page('Deuxième', 2);
        $this->page('Troisième', 3);
        $this->entityManager->flush();

        $html = $this->read('deuxieme');

        self::assertStringContainsString(sprintf('href="/fr/guide-%s/premier"', $this->suffix), $html);
        self::assertStringContainsString('rel="prev"', $html);
        self::assertStringContainsString(sprintf('href="/fr/guide-%s/troisieme"', $this->suffix), $html);
        self::assertStringContainsString('rel="next"', $html);
    }

    /** The ends of the sequence lead nowhere rather than wrapping around. */
    public function testTheFirstPageHasNoPrevious(): void
    {
        $this->page('Premier', 1);
        $this->page('Deuxième', 2);
        $this->entityManager->flush();

        $html = $this->read('premier');

        self::assertStringNotContainsString('rel="prev"', $html);
        self::assertStringContainsString('rel="next"', $html);
    }

    public function testTheLastPageHasNoNext(): void
    {
        $this->page('Premier', 1);
        $this->page('Deuxième', 2);
        $this->entityManager->flush();

        $html = $this->read('deuxieme');

        self::assertStringContainsString('rel="prev"', $html);
        self::assertStringNotContainsString('rel="next"', $html);
    }

    /** The summary names the tree, and marks where the reader is. */
    public function testTheSummaryListsTheRubricAndMarksTheCurrentPage(): void
    {
        $this->page('Premier', 1);
        $this->page('Deuxième', 2);
        $this->entityManager->flush();

        $html = $this->read('premier');

        // The nav, not the words: the rubric's name also appears in the term
        // chips at the foot of every page, so matching it alone would pass
        // with no summary at all.
        self::assertStringContainsString('aria-label="Sommaire"', $html);
        self::assertStringContainsString('Pour commencer', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }

    /**
     * The switch, which is the whole reason `sequence` exists as a support.
     *
     * Every type of content renders through this template. A summary that
     * appeared under an article would be a regression introduced by a feature
     * meant for one type.
     */
    public function testATypeThatIsNotReadInSequenceGetsNothing(): void
    {
        $this->type->setSupports(['blocks']);
        $this->page('Premier', 1);
        $this->page('Deuxième', 2);
        $this->entityManager->flush();

        $html = $this->read('premier');

        self::assertStringNotContainsString('rel="next"', $html);
        self::assertStringNotContainsString('aria-label="Sommaire"', $html);
    }

    private function read(string $slug): string
    {
        // The summary walks collections the controller loads from the
        // database; a manager still holding them from the writes above would
        // answer from memory and prove nothing.
        $this->entityManager->clear();

        $this->client->request('GET', sprintf('/fr/guide-%s/%s', $this->suffix, $slug));

        self::assertResponseIsSuccessful();

        return (string) $this->client->getResponse()->getContent();
    }

    private function page(string $title, int $position): Post
    {
        $post = new Post();
        $post->setPostType($this->type)
            ->setStatus(PostStatusEnum::Published)
            ->setPublishedAt(new DateTimeImmutable('-1 day'))
            ->setPosition($position)
            ->addTerm($this->rubric);

        $post->translate('fr')->setTitle($title)->setSlug(mb_strtolower(strtr($title, ['è' => 'e', 'é' => 'e'])));

        $this->entityManager->persist($post);
        $this->entityManager->flush();
        $this->track($post);

        return $post;
    }
}
