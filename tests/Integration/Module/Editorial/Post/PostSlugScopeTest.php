<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\Post;

use Aurora\Module\Editorial\Post\Entity\Post;
use Aurora\Module\Editorial\Post\Enum\PostStatusEnum;
use Aurora\Module\Editorial\PostType\Entity\PostType;
use Aurora\Tests\Integration\IntegrationTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function bin2hex;
use function random_bytes;
use function sprintf;

/**
 * Two types of content may hold the same address.
 *
 * The public route is `/{locale}/{postTypeSlug}/{slug}` and the lookup ignored
 * the type: the first publication with that slug won, whatever type it
 * belonged to. Where the two disagreed, the controller took it for a
 * publication that had changed type and answered a **permanent** redirect to
 * the other one - so the page asked for became unreachable, and browsers
 * cached the detour.
 *
 * It surfaced on the documentation, where a page called "tableau-de-bord" was
 * about to be written beside a card of the tour with exactly that address.
 * Both are legitimate, and neither is the other.
 *
 * The redirect itself has to survive: an address shared before a publication
 * changed type must still lead somewhere. So it stays, as the fall-back it
 * always meant to be.
 */
final class PostSlugScopeTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    /** @var list<array{class-string, int}> */
    private array $created = [];

    private string $slug;

    private PostType $first;

    private PostType $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $this->slug = 'tableau-de-bord-'.$suffix;
        $this->first = $this->type('tour-'.$suffix);
        $this->second = $this->type('manuel-'.$suffix);
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

    /** Each address answers its own page, with no redirect between them. */
    public function testEachTypeAnswersItsOwnPage(): void
    {
        $this->publish($this->first, 'La carte du tour');
        $this->publish($this->second, 'La page du manuel');

        foreach ([[$this->first, 'La carte du tour'], [$this->second, 'La page du manuel']] as [$type, $title]) {
            $this->client->request('GET', sprintf('/fr/%s/%s', $type->getSlug(), $this->slug));

            self::assertResponseIsSuccessful();
            self::assertStringContainsString($title, (string) $this->client->getResponse()->getContent());
        }
    }

    /**
     * The behaviour that had to survive the fix.
     *
     * An address shared before a publication changed type still leads to it,
     * by a permanent redirect - which is only reached now when no publication
     * of the named type answers.
     */
    public function testAnAddressUnderTheWrongTypeStillRedirects(): void
    {
        $this->publish($this->first, 'La carte du tour');

        $this->client->request('GET', sprintf('/fr/%s/%s', $this->second->getSlug(), $this->slug));

        self::assertResponseRedirects(sprintf('/fr/%s/%s', $this->first->getSlug(), $this->slug), 301);
    }

    private function type(string $slug): PostType
    {
        $type = new PostType();
        $type->setSlug($slug)->setLabel($slug)->setHasArchive(true);

        $this->entityManager->persist($type);
        $this->entityManager->flush();
        $this->created[] = [$type::class, (int) $type->getId()];

        return $type;
    }

    private function publish(PostType $type, string $title): void
    {
        $post = new Post();
        $post->setPostType($type)
            ->setStatus(PostStatusEnum::Published)
            ->setPublishedAt(new DateTimeImmutable('-1 day'));

        $post->translate('fr')->setTitle($title)->setSlug($this->slug);

        $this->entityManager->persist($post);
        $this->entityManager->flush();
        $this->created[] = [$post::class, (int) $post->getId()];
    }
}
