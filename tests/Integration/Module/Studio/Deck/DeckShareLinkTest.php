<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Studio\Deck;

use Aurora\Module\Studio\Deck\Enum\SlideLayoutEnum;
use Aurora\Module\Studio\Deck\Manager\DeckManager;
use Aurora\Module\Studio\Deck\Share\Entity\DeckShareLink;
use Aurora\Tests\Integration\IntegrationTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * What a share link lets somebody see, and what it must not.
 *
 * The interesting assertions are the negative ones. A link is a secret handed
 * to one person, so the three ways it can stop working have to stop working,
 * and the speaker notes have to stay on the presenter's side of the screen.
 */
final class DeckShareLinkTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testAValidLinkOpensTheDeckWithoutAnAccount(): void
    {
        $link = $this->link();

        $this->client->request('GET', '/decks/'.$link->getToken());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('PublicDeckApp', (string) $this->client->getResponse()->getContent());
    }

    /**
     * The one that would be embarrassing rather than merely wrong: the notes
     * are what the presenter says, and a single screen is the audience's.
     */
    public function testTheSpeakerNotesNeverReachThePage(): void
    {
        $link = $this->link('Ne pas dire que le budget est deja vote.');

        $this->client->request('GET', '/decks/'.$link->getToken());

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('budget est deja vote', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('speakerNotes', (string) $this->client->getResponse()->getContent());
    }

    public function testARevokedLinkIsRefused(): void
    {
        $link = $this->link();
        $link->revoke(new DateTimeImmutable());
        $this->entityManager()->flush();

        $this->client->request('GET', '/decks/'.$link->getToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnExpiredLinkIsRefused(): void
    {
        $link = $this->link();
        $link->setExpiresAt(new DateTimeImmutable('-1 day'));
        $this->entityManager()->flush();

        $this->client->request('GET', '/decks/'.$link->getToken());

        self::assertResponseStatusCodeSame(404);
    }

    /** A guessed address must learn nothing, including that it was close. */
    public function testAnUnknownTokenIs404(): void
    {
        $this->client->request('GET', '/decks/'.str_repeat('a', 64));

        self::assertResponseStatusCodeSame(404);
    }

    public function testOpeningTheLinkRecordsThatItWasOpened(): void
    {
        $link = $this->link();

        self::assertNull($link->getLastUsedAt());

        $this->client->request('GET', '/decks/'.$link->getToken());
        $this->entityManager()->refresh($link);

        self::assertNotNull($link->getLastUsedAt());
    }

    private function link(?string $notes = null): DeckShareLink
    {
        $container = static::getContainer();
        $decks = $container->get(DeckManager::class);

        $deck = $decks->create('Revue de fin d annee');
        $slide = $decks->addSlide($deck, SlideLayoutEnum::Title);
        $decks->writeContent($slide, ['title' => 'Revue de fin d annee']);
        $slide->setSpeakerNotes($notes);

        $link = new DeckShareLink($deck);
        $this->entityManager()->persist($link);
        $this->entityManager()->flush();

        return $link;
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
