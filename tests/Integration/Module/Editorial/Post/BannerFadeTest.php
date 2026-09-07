<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\Post;

use Aurora\Module\Editorial\Post\Banner\BannerViewBuilder;
use Aurora\Tests\Integration\IntegrationTestCase;
use Twig\Environment;

/**
 * The foot of a banner, which used to end on a hard edge with no way to soften
 * it - so a full-width header sat on the page as a block rather than opening
 * it.
 */
final class BannerFadeTest extends IntegrationTestCase
{
    private const string TEMPLATE = 'Frontend/themes/default/editorial/post/_banner.html.twig';

    private BannerViewBuilder $bannerViewBuilder;

    private Environment $twig;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->bannerViewBuilder = static::getContainer()->get(BannerViewBuilder::class);
        $twig = static::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);
        $this->twig = $twig;
    }

    public function testTheFootOfTheBannerCanDissolveIntoThePage(): void
    {
        self::assertStringContainsString('to-bg', $this->render(['fadeOut' => true]));
    }

    /** Off by default: a hard edge is what every published banner has. */
    public function testABannerKeepsItsEdgeUnlessAsked(): void
    {
        self::assertStringNotContainsString('to-bg', $this->render([]));
    }

    /** @param array<string, mixed> $overrides */
    private function render(array $overrides): string
    {
        $banner = $this->bannerViewBuilder->build(
            [
                'enabled' => true,
                'items' => [['id' => 'a1', 'type' => 'text']],
                ...$overrides,
            ],
            ['items' => ['a1' => ['title' => 'Bonjour']]],
        );

        self::assertNotNull($banner);

        return $this->twig->render(self::TEMPLATE, ['banner' => $banner]);
    }
}
