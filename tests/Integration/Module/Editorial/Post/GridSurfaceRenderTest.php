<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\Post;

use Aurora\Module\Editorial\Post\Grid\GridViewBuilder;
use Aurora\Tests\Integration\IntegrationTestCase;
use Twig\Environment;

/**
 * What a zone sits on, from the stored value to the markup.
 *
 * The surface is the one grid setting with no field of its own to show for it:
 * it adds no words, only a background, so nothing but the rendered page says
 * whether it worked. A view-builder test would assert that a string travelled;
 * this asserts that the page changed.
 */
final class GridSurfaceRenderTest extends IntegrationTestCase
{
    private const string TEMPLATE = 'Frontend/themes/default/editorial/post/_grid_zone.html.twig';

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

    public function testAZoneWithNoSurfaceIsWrappedInNothing(): void
    {
        $html = $this->render($this->zone());

        self::assertStringNotContainsString('bg-surface-2', $html);
        self::assertStringNotContainsString('w-screen', $html);
    }

    public function testACardDrawsItsOwnBox(): void
    {
        $html = $this->render($this->zone(['surface' => 'card']));

        self::assertStringContainsString('rounded-xl', $html);
        self::assertStringContainsString('border-line', $html);
    }

    public function testATintedZoneReadsAsOneSection(): void
    {
        self::assertStringContainsString('bg-surface-2', $this->render($this->zone(['surface' => 'soft'])));
    }

    /**
     * The band spans the viewport; the words do not. A paragraph set at the
     * width of a screen is unreadable, so the content goes back into the box
     * `<main>` uses - which is also what keeps a full-width section lined up
     * with the title above it.
     */
    public function testAFullWidthBandHoldsItsWordsAtThePagesWidth(): void
    {
        $html = $this->render($this->zone(['surface' => 'accent', 'fullBleed' => true]));

        self::assertStringContainsString('w-screen', $html);
        self::assertStringContainsString('max-w-7xl', $html);
        // A band with rounded ends reads as a card that has overflowed.
        self::assertStringNotContainsString('rounded-xl', $html);
    }

    /**
     * A call to action is a title, a line and a button in one band, which in
     * this grid is a stack - so a stack that cannot have a background is a
     * call to action that cannot be built.
     */
    public function testAStackSitsOnItsSurfaceToo(): void
    {
        $grid = $this->gridViewBuilder->build(
            [
                'enabled' => true,
                'zones' => [[
                    'id' => 's1',
                    'type' => 'stack',
                    'surface' => 'accent',
                    'fullBleed' => true,
                    'children' => [['id' => 'c1', 'type' => 'text']],
                ]],
            ],
            ['zones' => ['c1' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Parlons-en.']]]]]],
            'fr',
        );

        self::assertNotNull($grid);

        $html = $this->twig->render(
            'Frontend/themes/default/editorial/post/_grid.html.twig',
            ['grid' => $grid, 'locale' => 'fr'],
        );

        self::assertStringContainsString('w-screen', $html);
        self::assertStringContainsString('bg-accent-500/10', $html);
        self::assertStringContainsString('Parlons-en.', $html);
    }

    /** @param array<string, mixed> $overrides */
    private function zone(array $overrides = []): array
    {
        $grid = $this->gridViewBuilder->build(
            [
                'enabled' => true,
                'zones' => [['id' => 'z1', 'type' => 'text', ...$overrides]],
            ],
            ['zones' => ['z1' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Une phrase.']]]]]],
            'fr',
        );

        self::assertNotNull($grid);

        return $grid['zones'][0];
    }

    /** @param array<string, mixed> $zone */
    private function render(array $zone): string
    {
        return $this->twig->render(self::TEMPLATE, ['zone' => $zone, 'locale' => 'fr']);
    }
}
