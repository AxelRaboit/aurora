<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

use Aurora\Core\Money\Enum\CurrencyEnum;
use Aurora\Core\Support\Str;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function mb_trim;
use function preg_match;
use function preg_replace;
use function round;
use function str_replace;

#[AsAlias(ContractInputFactoryInterface::class)]
class ContractInputFactory implements ContractInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractInputInterface
    {
        $amount = $this->centsFromArray($data, 'amount');

        return new ContractInput(
            customerId: $this->idOrNull($data, 'customerId'),
            bodyTemplateId: $this->idOrNull($data, 'bodyTemplateId'),
            annexTemplateId: $this->idOrNull($data, 'annexTemplateId'),
            locale: Str::trimFromArray($data, 'locale', 'fr'),
            amountCents: $amount,
            // The currency exists exactly when the amount does: a currency on
            // its own says nothing, and an amount without one cannot be
            // printed into a contract.
            amountCurrency: null === $amount ? null : $this->currency($data),
            effectiveDate: $this->rawOrNull($data, 'effectiveDate'),
            customFields: $this->customFields($data['customFields'] ?? null),
        );
    }

    /**
     * The map, trimmed, with anything unusable dropped.
     *
     * A key is kept only when it could appear in a trame - lowercase, digits
     * and underscores. An empty value is kept rather than dropped: the freeze
     * is the place that refuses a blank, and it can only name a missing field
     * if it sees the key.
     *
     * @return array<string, string>
     */
    private function customFields(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $fields = [];

        foreach ($raw as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (1 !== preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $fields[$key] = mb_trim((string) $value);
        }

        return $fields;
    }

    /** @param array<string, mixed> $data */
    private function currency(array $data): string
    {
        $currency = CurrencyEnum::tryFrom(Str::trimFromArray($data, 'amountCurrency'));

        return null === $currency ? CurrencyEnum::EUR->value : $currency->value;
    }

    /**
     * A typed amount as cents, both separators accepted.
     *
     * The same reader as the customer factory's, and for the same reason: the
     * field is typed by a person, and a French keyboard's comma has to mean
     * what an English one's dot means.
     *
     * @param array<string, mixed> $data
     */
    private function centsFromArray(array $data, string $key): ?int
    {
        $raw = $this->rawOrNull($data, $key);

        if (null === $raw) {
            return null;
        }

        $normalized = str_replace(',', '.', (string) preg_replace('/\s|\x{00A0}|\x{202F}/u', '', $raw));

        if (!is_numeric($normalized)) {
            return null;
        }

        return (int) round((float) $normalized * 100);
    }

    /**
     * Not `Str::trimOrNullFromArray`: that helper ends in `?: null`, so the
     * string "0" comes back as null.
     *
     * @param array<string, mixed> $data
     */
    private function rawOrNull(array $data, string $key): ?string
    {
        if (!isset($data[$key])) {
            return null;
        }

        $trimmed = mb_trim((string) $data[$key]);

        return '' === $trimmed ? null : $trimmed;
    }

    /** @param array<string, mixed> $data */
    private function idOrNull(array $data, string $key): ?int
    {
        $raw = $data[$key] ?? null;

        return is_numeric($raw) ? (int) $raw : null;
    }
}
