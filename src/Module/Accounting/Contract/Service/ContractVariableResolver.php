<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Service;

use Aurora\Core\Money\Enum\CurrencyEnum;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;
use DateTimeImmutable;
use IntlDateFormatter;
use NumberFormatter;

use function preg_match;
use function preg_replace;
use function sprintf;

/**
 * Turns a contract into the values its tokens stand for.
 *
 * Only the freeze-time tokens. The two signature-time ones are left alone on
 * purpose, and this class is where that is enforced rather than remembered:
 * `resolve()` returns no entry for them, so a substitution pass has nothing to
 * put in their place and leaves the token standing.
 *
 * Formatting happens here, once, and that is deliberate. An amount printed
 * into a contract is read by a person in a language, so it goes through ICU
 * with the contract's own locale - and the same amount always prints the same
 * way, which is what a hash of the rendered document requires.
 */
final readonly class ContractVariableResolver
{
    public function __construct(private ContractVariableCatalogue $catalogue) {}

    /**
     * Every token this contract can fill in today, keyed without braces.
     *
     * A field nobody filled in comes back as an empty string rather than being
     * absent: the token is known, its value is simply not there yet, and a
     * document with a visible blank is better than one carrying `{{…}}` into a
     * signature.
     *
     * @return array<string, string>
     */
    public function resolve(ContractInterface $contract): array
    {
        $customer = $contract->getCustomer();
        $locale = $contract->getLocale();

        return [
            'customer.legal_name' => $customer->getLegalName(),
            'customer.legal_form' => $customer->getLegalForm() ?? '',
            'customer.share_capital' => $this->capital($customer, $locale),
            'customer.registered_office' => $customer->getRegisteredOffice() ?? '',
            'customer.siret' => $this->groupedSiret($customer->getSiret()),
            'customer.trade_register' => $customer->getTradeRegister() ?? '',
            'customer.vat_number' => $customer->getVatNumber() ?? '',
            'customer.activity_sector' => $customer->getActivitySector() ?? '',
            'customer.representative_full_name' => $customer->getRepresentativeFullName() ?? '',
            'customer.representative_role' => $customer->getRepresentativeRole() ?? '',
            'customer.contractual_email' => $customer->getContractualEmail(),
            'customer.phone' => $customer->getPhone() ?? '',
            'contract.reference' => $contract->getReference() ?? '',
            'contract.amount' => $this->amount($contract, $locale),
            'contract.effective_date' => $this->date($contract, $locale),
        ];
    }

    /**
     * The tokens a document may still carry after a resolve.
     *
     * @return list<string>
     */
    public function deferredTokens(): array
    {
        return $this->catalogue->signatureTokens();
    }

    private function capital(CustomerInterface $customer, string $locale): string
    {
        $cents = $customer->getShareCapitalCents();

        if (null === $cents) {
            return '';
        }

        // The currency exists exactly when the amount does, which the factory
        // guarantees; the fallback is for rows written before that rule.
        $currency = $customer->getShareCapitalCurrency();

        return $this->money($cents, $currency instanceof CurrencyEnum ? $currency->value : 'EUR', $locale);
    }

    private function amount(ContractInterface $contract, string $locale): string
    {
        $cents = $contract->getAmountCents();

        if (null === $cents) {
            return '';
        }

        $currency = $contract->getAmountCurrency();

        return $this->money($cents, $currency instanceof CurrencyEnum ? $currency->value : 'EUR', $locale);
    }

    private function money(int $cents, string $currency, string $locale): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        // A round amount prints without decimals, which is how a contract
        // states a monthly fee. The two-decimal form is kept for the amounts
        // that need it rather than imposed on those that do not.
        if (0 === $cents % 100) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);
        }

        return (string) $formatter->formatCurrency($cents / 100, $currency);
    }

    private function date(ContractInterface $contract, string $locale): string
    {
        $date = $contract->getEffectiveDate();

        if (!$date instanceof DateTimeImmutable) {
            return '';
        }

        // The short form, because that is what a contract writes: 01/10/2026,
        // not "1 octobre 2026". ICU rather than a hardcoded format, so the
        // Spanish version of the same contract reads as a Spanish date.
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE);
        $formatter->setPattern($this->datePattern($locale));

        return (string) $formatter->format($date);
    }

    /**
     * A four-digit year, whatever the locale's short form does.
     *
     * ICU's short date gives a two-digit year in several locales, and "le
     * 01/10/26" in a contract is an ambiguity nobody should have to resolve a
     * decade later.
     */
    private function datePattern(string $locale): string
    {
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE);
        $pattern = (string) $formatter->getPattern();

        if (1 === preg_match('/y{4}/', $pattern)) {
            return $pattern;
        }

        return (string) preg_replace('/y{1,3}/', 'yyyy', $pattern);
    }

    /**
     * A SIRET in the groups it is printed in.
     *
     * Stored as fourteen digits, read as 732 829 320 00074. The contract shows
     * the printed form because that is what somebody checks it against.
     */
    private function groupedSiret(?string $siret): string
    {
        if (null === $siret) {
            return '';
        }

        if (1 !== preg_match('/^(\d{3})(\d{3})(\d{3})(\d{5})$/', $siret, $parts)) {
            return $siret;
        }

        return sprintf('%s %s %s %s', $parts[1], $parts[2], $parts[3], $parts[4]);
    }
}
