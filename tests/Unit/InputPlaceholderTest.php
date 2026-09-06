<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function dirname;
use function file_get_contents;
use function preg_match_all;
use function sprintf;
use function str_contains;

/**
 * Every text field carries a placeholder.
 *
 * A label says what the field is; a placeholder says what a value looks like,
 * and the two answer different questions. Sixty-two fields across twelve screens
 * had only the first, which is the kind of gap nobody reports and everybody
 * works around - you type something, it is refused, and then you learn the
 * format.
 *
 * Asserted on the attribute rather than its text. What a good placeholder says
 * is a judgement, and this is not the place for it; what is checkable is that
 * somebody made the decision at all. A field whose example is only known at
 * runtime satisfies this with a binding - the post-type custom fields read
 * `field.options?.placeholder` and fall back - which is the right answer for
 * them and would be impossible to check any other way.
 */
final class InputPlaceholderTest extends TestCase
{
    /**
     * The shared components that render a text box. `AppSelect` is absent on
     * purpose: a select's empty state is its own prop, and the codebase already
     * has `select_placeholder` for it.
     */
    private const array COMPONENTS = [
        'AppInput',
        'AppTextarea',
        'AppSearchInput',
        'AppAmountInput',
        'AppTagsInput',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function singleFileComponents(): iterable
    {
        $root = dirname(__DIR__, 2).'/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || 'vue' !== $file->getExtension()) {
                continue;
            }

            $path = $file->getPathname();
            $contents = (string) file_get_contents($path);

            $uses = false;
            foreach (self::COMPONENTS as $component) {
                if (str_contains($contents, '<'.$component)) {
                    $uses = true;

                    break;
                }
            }

            if (!$uses) {
                continue;
            }

            yield str_replace($root.'/', '', $path) => [$path];
        }
    }

    #[DataProvider('singleFileComponents')]
    public function testEveryTextFieldOffersAnExample(string $path): void
    {
        $contents = (string) file_get_contents($path);

        foreach (self::COMPONENTS as $component) {
            // The whole tag, attributes across as many lines as it takes. The
            // lazy quantifier stops at the first `>`, which is what closes it -
            // an attribute value containing one would break this, and none does.
            preg_match_all('/<'.$component.'\b[^>]*>/s', $contents, $matches);

            foreach ($matches[0] as $tag) {
                self::assertStringContainsString(
                    'placeholder',
                    $tag,
                    sprintf(
                        '%s: a <%s> with no placeholder. A label says what the field is; a placeholder says what a value looks like.',
                        $path,
                        $component,
                    ),
                );
            }
        }
    }

    /**
     * The provider has to find something, or the check above passes by finding
     * nothing - which is how a renamed component silently retires a test.
     */
    public function testTheScanFindsTheScreensItIsMeantTo(): void
    {
        self::assertGreaterThan(30, count([...self::singleFileComponents()]));
    }

    /**
     * And the fields written by hand.
     *
     * The check above only ever saw the shared components, so a screen built
     * from plain `<input>` was invisible to it - which is exactly what the
     * public comment form was: three fields, three labels, not one example,
     * and a test on the subject that had nothing to say about them.
     *
     * @return iterable<string, array{string}>
     */
    public static function vueFiles(): iterable
    {
        $root = dirname(__DIR__, 2).'/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || 'vue' !== $file->getExtension()) {
                continue;
            }

            $path = $file->getPathname();

            // Only the files with something to check: a component whose every
            // field is a checkbox would otherwise be a case that asserts
            // nothing, which PHPUnit rightly calls risky.
            if ([] === self::checkableFields((string) file_get_contents($path))) {
                continue;
            }

            yield str_replace($root.'/', '', $path) => [$path];
        }
    }

    /**
     * The hand-written fields of one file that could carry an example.
     *
     * @return list<array{string, string}> the whole tag, and what it is
     */
    private static function checkableFields(string $contents): array
    {
        preg_match_all('/<(input|textarea)\b[^>]*>/s', $contents, $matches, PREG_SET_ORDER);

        $fields = [];

        foreach ($matches as $match) {
            [$tag, $element] = $match;

            $type = 'textarea' === $element ? 'textarea' : 'text';
            if (1 === preg_match('/\btype="([^"]+)"/', $tag, $found)) {
                $type = $found[1];
            }

            if (in_array($type, self::TYPELESS, true)
                || str_contains($tag, 'readonly')
                || str_contains($tag, 'aria-hidden="true"')
            ) {
                continue;
            }

            $fields[] = [$tag, $element];
        }

        return $fields;
    }

    /**
     * Types that hold no text hold no example either: a checkbox, a colour, a
     * file. Nor does a field nobody types into - one that is read-only, or the
     * honeypot, which is hidden from readers and from screen readers alike and
     * whose whole point is that a human never sees it.
     */
    private const array TYPELESS = [
        'hidden', 'checkbox', 'radio', 'color', 'file',
        'range', 'submit', 'button', 'reset', 'image',
    ];

    #[DataProvider('vueFiles')]
    public function testEveryHandWrittenFieldOffersAnExampleToo(string $path): void
    {
        foreach (self::checkableFields((string) file_get_contents($path)) as [$tag, $element]) {
            self::assertStringContainsString(
                'placeholder',
                $tag,
                sprintf(
                    '%s: a hand-written <%s> with no placeholder. A label says what the field is; a placeholder says what a value looks like.',
                    $path,
                    $element,
                ),
            );
        }
    }
}
