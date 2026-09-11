<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Documentation;

use Aurora\Module\Platform\User\Repository\UserRepository;
use Aurora\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function json_decode;
use function rawurlencode;

use const JSON_THROW_ON_ERROR;

/**
 * The manual, served from the package.
 *
 * Three things are worth a test here, and they are the three that would let
 * something out: that the pages actually ship, that the screenshots are
 * served and cannot be used to read anything else on disk, and that none of
 * it is readable without signing in.
 */
final class DocumentationPageTest extends IntegrationTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testTheIndexOpensOnTheFirstPage(): void
    {
        $this->signIn();
        $this->client->request('GET', '/backend/documentation');

        self::assertResponseRedirects();
        self::assertStringStartsWith('/backend/documentation/', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAPageIsRendered(): void
    {
        $this->signIn();
        $html = $this->read('/backend/documentation/cycle-de-vie');

        self::assertStringContainsString('DocumentationApp', $html);
        self::assertStringContainsString('Le cycle de vie', $html);
    }

    public function testAnUnknownPageIs404(): void
    {
        $this->signIn();
        $this->client->request('GET', '/backend/documentation/ce-slug-n-existe-pas');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheSearchAnswersFromTheFiles(): void
    {
        $this->signIn();
        $this->client->request('GET', '/backend/documentation/search?q=depublier');

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertNotEmpty($payload['results']);
    }

    /**
     * The side menu's panel has no payload of its own - the menu mounts it
     * with no props - so this route is the only way it learns what the manual
     * contains.
     */
    public function testTheTreeIsServedForThePanel(): void
    {
        $this->signIn();
        $this->client->request('GET', '/backend/documentation/tree');

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertNotEmpty($payload['tree']);
        self::assertNotEmpty($payload['tree'][0]['pages']);
    }

    public function testAScreenshotIsServed(): void
    {
        $this->signIn();
        $this->client->request('GET', '/backend/documentation/image/02-editorial/cycle-de-vie-01-les-statuts-dans-la-liste.png');

        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * The route pattern already refuses a slash, so this asks the question
     * the pattern cannot: a name that resolves outside the image folder must
     * not be served, whatever route matched it.
     */
    public function testAPathOutsideTheImageFolderIsRefused(): void
    {
        $this->signIn();
        $this->client->request('GET', '/backend/documentation/image/02-editorial/'.rawurlencode('../../../composer.json'));

        self::assertResponseStatusCodeSame(404);
    }

    /** The manual describes the back office, so it lives behind its door. */
    public function testAVisitorIsSentToTheLogin(): void
    {
        $this->client->request('GET', '/backend/documentation/cycle-de-vie');

        self::assertResponseRedirects();
        self::assertStringContainsString('login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    private function signIn(): void
    {
        $admin = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'dev@aurora.app', 'type' => 'backend']);

        $this->client->loginUser($admin, 'admin');
    }

    private function read(string $path): string
    {
        $this->client->request('GET', $path);

        self::assertResponseIsSuccessful();

        return (string) $this->client->getResponse()->getContent();
    }
}
