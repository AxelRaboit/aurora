<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Locale\Enum;

use Aurora\Core\Locale\Enum\LocaleEnum;
use PHPUnit\Framework\TestCase;

final class LocaleEnumTest extends TestCase
{
    public function testValuesReturnsAllCases(): void
    {
        self::assertSame(['fr', 'en', 'es'], LocaleEnum::values());
    }

    public function testIsSupportedReturnsTrueForKnownLocales(): void
    {
        self::assertTrue(LocaleEnum::isSupported('fr'));
        self::assertTrue(LocaleEnum::isSupported('en'));
        self::assertTrue(LocaleEnum::isSupported('es'));
    }

    public function testIsSupportedReturnsFalseForUnknownLocale(): void
    {
        self::assertFalse(LocaleEnum::isSupported('de'));
        self::assertFalse(LocaleEnum::isSupported(''));
        self::assertFalse(LocaleEnum::isSupported('fr_FR'));
    }

    public function testDefaultIsFrench(): void
    {
        self::assertSame(LocaleEnum::French, LocaleEnum::default());
    }
}
