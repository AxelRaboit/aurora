<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\Post;

use Aurora\Module\Editorial\Post\Grid\GridViewBuilder;
use Aurora\Tests\Integration\IntegrationTestCase;
use Twig\Environment;

/**
 * The two costumes an item list gained, from the stored entry to the markup.
 *
 * Both reuse the four fields the other five already share, so what is worth
 * checking is not that the words travel - the builder is tested for that - but
 * that each costume draws the thing it promises: a date in the margin of a
 * history, a price and a recommended card on a page of offers.
 */
final class GridItemDisplaysTest extends IntegrationTestCase
{
    private GridViewBuilder $gridViewBuilder;

    private Environment $twig;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->gridViewBuilder = static::getContainer()->get(GridViewBuilder::class);
        $twig = static::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);
        $this->twig = $twig;
    }

    public function testATimelinePutsItsDateInTheMargin(): void
    {
        $html = $this->render('timeline', [
            ['id' => 'i1'],
        ], ['i1' => ['caption' => '2024', 'title' => 'Lancement', 'description' => 'Le studio ouvre.']]);

        self::assertStringContainsString('2024', $html);
        self::assertStringContainsString('Lancement', $html);
        // The date leads, above the title it belongs to.
        self::assertLessThan(mb_strpos($html, 'Lancement'), (int) mb_strpos($html, '2024'));
    }

    /** One line per line: a list of what is included has to read as a list. */
    public function testAnOfferTurnsEachLineIntoABullet(): void
    {
        $html = $this->render('offers', [
            ['id' => 'i1'],
        ], ['i1' => [
            'title' => 'Site vitrine',
            'caption' => 'à partir de 1 500 €',
            'description' => "Cinq pages\nUn formulaire\n",
        ]]);

        self::assertSame(2, mb_substr_count($html, '<li'), 'a blank line is not an item');
        self::assertStringContainsString('Cinq pages', $html);
        self::assertStringContainsString('à partir de 1 500 €', $html);
    }

    public function testTheRecommendedOfferIsDrawnLouderThanTheOthers(): void
    {
        $plain = $this->render('offers', [['id' => 'i1']], ['i1' => ['title' => 'Simple']]);
        $featured = $this->render(
            'offers',
            [['id' => 'i1', 'featured' => true]],
            ['i1' => ['title' => 'Simple']],
        );

        self::assertStringNotContainsString('border-accent', $plain);
        self::assertStringContainsString('border-accent', $featured);
        self::assertStringContainsString('Recommandé', $featured);
    }

    /**
     * @param list<array<string, mixed>>          $items
     * @param array<string, array<string, mixed>> $words
     */
    private function render(string $display, array $items, array $words): string
    {
        $grid = $this->gridViewBuilder->build(
            [
                'enabled' => true,
                'zones' => [['id' => 'z1', 'type' => 'items', 'display' => $display, 'items' => $items]],
            ],
            ['zones' => ['z1' => ['items' => $words]]],
            'fr',
        );

        self::assertNotNull($grid);

        return $this->twig->render(
            'Frontend/themes/default/editorial/post/_grid_zone.html.twig',
            ['zone' => $grid['zones'][0], 'locale' => 'fr'],
        );
    }
}
