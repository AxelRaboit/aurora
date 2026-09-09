<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Accounting\Customer\Dto;

use Aurora\Core\Money\Enum\CurrencyEnum;
use Aurora\Module\Accounting\Customer\Dto\CustomerInputFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The factory is where a typed amount and a copy-pasted SIRET become storable.
 *
 * Both are read off paper by a person: the amount arrives with whichever
 * decimal separator their keyboard produces, the number with the spaces it is
 * printed in. Getting either wrong writes a plausible but false value into
 * something that later gets signed.
 */
final class CustomerInputFactoryTest extends TestCase
{
    private CustomerInputFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CustomerInputFactory();
    }

    public static function amounts(): iterable
    {
        yield 'plain integer' => ['10000', 1000000];
        yield 'french decimal' => ['1500,50', 150050];
        yield 'english decimal' => ['1500.50', 150050];
        yield 'thin space grouping' => ["10\u{202F}000", 1000000];
        yield 'non-breaking space grouping' => ["10\u{00A0}000,25", 1000025];
        yield 'plain space grouping' => ['10 000', 1000000];
        yield 'zero' => ['0', 0];
    }

    #[DataProvider('amounts')]
    public function testShareCapitalIsReadAsCents(string $typed, int $expected): void
    {
        $input = $this->factory->fromArray(['shareCapital' => $typed]);

        self::assertSame($expected, $input->getShareCapitalCents());
    }

    public function testAnUnreadableAmountIsNullRatherThanZero(): void
    {
        $input = $this->factory->fromArray(['shareCapital' => 'beaucoup']);

        // Null, not 0: a capital of nothing and a capital nobody typed are two
        // different statements, and only one of them belongs in a contract.
        self::assertNull($input->getShareCapitalCents());
    }

    public function testTheCurrencyOnlyExistsWithAnAmount(): void
    {
        self::assertNull($this->factory->fromArray([])->getShareCapitalCurrency());

        self::assertSame(
            CurrencyEnum::EUR,
            $this->factory->fromArray(['shareCapital' => '1000'])->getShareCapitalCurrency(),
        );

        self::assertSame(
            CurrencyEnum::CHF,
            $this->factory->fromArray([
                'shareCapital' => '1000',
                'shareCapitalCurrency' => 'CHF',
            ])->getShareCapitalCurrency(),
        );
    }

    public function testAnUnknownCurrencyFallsBackToEuroRatherThanFailing(): void
    {
        $input = $this->factory->fromArray([
            'shareCapital' => '1000',
            'shareCapitalCurrency' => 'XYZ',
        ]);

        self::assertSame(CurrencyEnum::EUR, $input->getShareCapitalCurrency());
    }

    public function testTheSiretKeepsOnlyItsDigits(): void
    {
        $input = $this->factory->fromArray(['siret' => '904 512 336 00010']);

        self::assertSame('90451233600010', $input->getSiret());
    }

    /**
     * A wrong number is handed on rather than swallowed: the constraint is what
     * reports it, and turning it into null here would save a row with no SIRET
     * and no complaint.
     */
    public function testAMalformedSiretReachesTheConstraint(): void
    {
        $input = $this->factory->fromArray(['siret' => '12-34']);

        self::assertSame('1234', $input->getSiret());
    }

    public function testTheEmailIsLoweredAndTrimmed(): void
    {
        $input = $this->factory->fromArray(['contractualEmail' => '  Contact@Societe.FR ']);

        self::assertSame('contact@societe.fr', $input->getContractualEmail());
    }

    public function testAnAbsentAccountIsNull(): void
    {
        self::assertNull($this->factory->fromArray([])->getUserId());
        self::assertNull($this->factory->fromArray(['userId' => ''])->getUserId());
        self::assertSame(7, $this->factory->fromArray(['userId' => '7'])->getUserId());
    }
}
