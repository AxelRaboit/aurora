<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Content;

use Aurora\Core\Content\BlockHtmlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Every colour the editor offers has to survive the sanitizer.
 *
 * The two halves are written in different languages and neither imports the
 * other: the palette is a list in `TextColorTool.js`, the rule about what may
 * sit in a `style` attribute is in {@see BlockHtmlSanitizer}. That gap has
 * already cost this application once - the inline tools were shipping styles
 * that were stripped on the public page, so an author picked a colour, saw it
 * in the editor, and saw nothing on the site.
 *
 * Reading the JavaScript rather than restating its values: a copy would agree
 * with itself while disagreeing with what the editor actually writes.
 */
#[CoversClass(BlockHtmlSanitizer::class)]
final class TextColorPaletteContractTest extends TestCase
{
    private const string TOOL = __DIR__.'/../../../../src/Core/assets/shared/components/editor/tools/TextColorTool.js';

    public function testEveryColourTheEditorOffersReachesThePage(): void
    {
        $palette = $this->palette();

        self::assertNotEmpty($palette, 'the palette could not be read - has the tool moved?');

        $sanitizer = new BlockHtmlSanitizer();

        foreach ($palette as $color) {
            $html = $sanitizer->safe(sprintf(
                '<span class="cdx-text-color" style="color: %s">mot</span>',
                $color,
            ));

            self::assertStringContainsString(
                'color: '.mb_strtolower($color),
                $html,
                sprintf('"%s" is offered in the editor and dropped on the page', $color),
            );
        }
    }

    /**
     * The palette's values, read out of the tool that declares them.
     *
     * @return list<string>
     */
    private function palette(): array
    {
        $source = (string) file_get_contents(self::TOOL);
        $block = mb_substr($source, 0, mb_strpos($source, '];') ?: 0);

        preg_match_all('/value:\s*"([^"]+)"/', $block, $matches);

        return $matches[1];
    }
}
