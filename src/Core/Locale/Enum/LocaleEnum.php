<?php

declare(strict_types=1);

namespace Aurora\Core\Locale\Enum;

enum LocaleEnum: string
{
    case French = 'fr';
    case English = 'en';
    case Spanish = 'es';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isSupported(string $locale): bool
    {
        return null !== self::tryFrom($locale);
    }

    public static function default(): self
    {
        return self::French;
    }
}
