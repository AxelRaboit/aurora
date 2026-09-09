<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\Taxonomy;

use Aurora\Module\Editorial\Taxonomy\Entity\Taxonomy;
use Aurora\Module\Editorial\Taxonomy\Entity\TaxonomyTerm;
use Aurora\Tests\Integration\IntegrationTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function bin2hex;
use function random_bytes;
use function sprintf;

/**
 * Where a term's address has to be unique.
 *
 * It used to be unique across every taxonomy at once, on the grounds that the
 * address named exactly one term. The route says otherwise - it is
 * `/{locale}/{taxonomySlug}/{termSlug}`, and the controller looks the term up
 * inside the taxonomy the URL names - so the wider rule bought nothing and
 * refused legitimate content: a tag and a documentation rubric may both be
 * called "Éditorial".
 *
 * Two things are worth pinning. The same address in two taxonomies is allowed,
 * and both pages answer. The same address twice in one taxonomy is still
 * refused, because that one really would be ambiguous.
 */
final class TermSlugScopeTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    /** @var list<int> taxonomy ids, cleaned by SQL so a closed manager cannot block it */
    private array $created = [];

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->slug = 'editorial-'.bin2hex(random_bytes(4));
    }

    /**
     * Cleaned through the connection rather than the manager.
     *
     * One of these tests provokes a constraint violation, and a violated
     * constraint closes the manager: a tidy-up that went through it would fail
     * on the way out and leave the rows behind. The cascade takes the terms
     * and their translations with the taxonomy.
     */
    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();

        foreach ($this->created as $id) {
            $connection->executeStatement('DELETE FROM core_taxonomies WHERE id = ?', [$id]);
        }

        $this->created = [];

        parent::tearDown();
    }

    /** The refusal that had no reason to exist. */
    public function testTwoTaxonomiesMayShareATermAddress(): void
    {
        $first = $this->taxonomy('etiquette');
        $second = $this->taxonomy('rubrique');

        $this->term($first, 'Éditorial');
        $this->term($second, 'Éditorial');

        $this->entityManager->flush();

        self::assertSame(2, $this->countTermsAt($this->slug));
    }

    /** Both pages answer, which is what the address being unambiguous means. */
    public function testBothPagesAnswer(): void
    {
        $first = $this->taxonomy('etiquette');
        $second = $this->taxonomy('rubrique');

        $this->term($first, 'Éditorial');
        $this->term($second, 'Éditorial');

        $this->entityManager->flush();

        $addresses = [
            sprintf('/fr/%s/%s', $first->getSlug(), $this->slug),
            sprintf('/fr/%s/%s', $second->getSlug(), $this->slug),
        ];

        // The inverse collection of a taxonomy is only filled when it comes
        // from the database, and the controller walks it to find the term.
        $this->entityManager->clear();

        foreach ($addresses as $address) {
            $this->client->request('GET', $address);

            self::assertResponseIsSuccessful();
        }
    }

    /** Inside one taxonomy, the address still has to name one term. */
    public function testOneTaxonomyStillRefusesTheSameAddressTwice(): void
    {
        $taxonomy = $this->taxonomy('etiquette');

        $this->term($taxonomy, 'Éditorial');
        $this->term($taxonomy, 'Éditorial bis');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function countTermsAt(string $slug): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM core_taxonomy_term_translations WHERE slug = ? AND locale = ?',
            [$slug, 'fr'],
        );
    }

    private function taxonomy(string $prefix): Taxonomy
    {
        $taxonomy = new Taxonomy();
        $taxonomy->setSlug($prefix.'-'.bin2hex(random_bytes(4)))->setHierarchical(false);
        $taxonomy->translate('fr')->setLabel('Test');

        $this->entityManager->persist($taxonomy);
        $this->entityManager->flush();
        $this->created[] = (int) $taxonomy->getId();

        return $taxonomy;
    }

    private function term(Taxonomy $taxonomy, string $name): TaxonomyTerm
    {
        $term = new TaxonomyTerm();
        $term->setTaxonomy($taxonomy);
        $term->translate('fr')->setName($name)->setSlug($this->slug);

        $this->entityManager->persist($term);

        return $term;
    }
}
