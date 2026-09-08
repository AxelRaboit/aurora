<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Locale;

use Aurora\Core\Locale\Enum\LocaleEnum;
use PHPUnit\Framework\TestCase;

/**
 * Every language the application offers has to reach the Vue components.
 *
 * Twig reads the YAML catalogues, so a language added to {@see LocaleEnum} is
 * translated on the server the moment its files exist. The components do not:
 * they are handed a bundle assembled in `i18n.js`, one import per locale,
 * written by hand.
 *
 * Spanish spent a fortnight in that gap. The page around a contact form was in
 * Spanish and its submit button said "Envoyer", because `es.json` was being
 * generated and never imported, so vue-i18n fell through to French for every
 * key a component asked for.
 *
 * Reading the JavaScript rather than restating what it should contain: a copy
 * of the list would agree with itself while disagreeing with the bundle the
 * browser is served.
 */
final class LocaleBundleTest extends TestCase
{
    private const string BUNDLE = __DIR__.'/../../../../src/Core/assets/i18n.js';

    private const string GENERATED = __DIR__.'/../../../../src/Core/assets/locales/generated';

    public function testTheVueBundleCarriesEveryLocale(): void
    {
        $source = (string) file_get_contents(self::BUNDLE);

        // The map handed to createI18n, which is what decides whether a locale
        // exists at all - an import nobody puts in `messages` is dead weight.
        preg_match('/messages:\s*\{([^}]*)\}/', $source, $matches);

        self::assertArrayHasKey(1, $matches, 'the messages map could not be read - has i18n.js been rewritten?');

        $registered = array_map(mb_trim(...), explode(',', $matches[1]));

        foreach (LocaleEnum::values() as $locale) {
            self::assertContains(
                $locale,
                $registered,
                sprintf('"%s" is offered by the application and absent from the Vue bundle, so its components render in French', $locale),
            );
        }
    }

    /** An import of a file the dump command never wrote breaks the build. */
    public function testEveryLocaleHasAGeneratedCatalogue(): void
    {
        foreach (LocaleEnum::values() as $locale) {
            self::assertFileExists(
                self::GENERATED.'/'.$locale.'.json',
                sprintf('run `php bin/console app:translations:dump-js` - "%s" has no bundle', $locale),
            );
        }
    }
}
