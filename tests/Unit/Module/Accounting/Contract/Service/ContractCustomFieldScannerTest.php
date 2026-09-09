<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Accounting\Contract\Service;

use Aurora\Module\Accounting\Contract\Service\ContractCustomFieldScanner;
use PHPUnit\Framework\TestCase;

/**
 * The wording is what declares a per-contract blank.
 *
 * So the scanner has one job and it has to be complete: a token missed here is
 * a field nobody is asked for, which reaches the signer as literal braces in
 * the middle of a clause. Hence the cases below cover every place a token can
 * hide rather than the happy path alone.
 */
final class ContractCustomFieldScannerTest extends TestCase
{
    private ContractCustomFieldScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new ContractCustomFieldScanner();
    }

    public function testAParagraphDeclaresItsField(): void
    {
        $keys = $this->scanner->keysIn(['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => 'Un acompte de {{contract.custom.acompte}} est dû.']],
        ]]);

        self::assertSame(['acompte'], $keys);
    }

    /**
     * A token can sit anywhere a person can type, and a block type added
     * tomorrow must not need this class to be updated - which is why it reads
     * the encoded blocks rather than walking known shapes.
     */
    public function testItFindsTokensInEveryShapeOfBlock(): void
    {
        $keys = $this->scanner->keysIn(['blocks' => [
            ['type' => 'header', 'data' => ['text' => '{{contract.custom.titre}}', 'level' => 2]],
            ['type' => 'list', 'data' => ['items' => [['content' => '{{contract.custom.seuil_km}}']]]],
            ['type' => 'table', 'data' => ['content' => [['a', '{{contract.custom.tarif_km}}']]]],
            ['type' => 'quote', 'data' => ['text' => 'x', 'caption' => '{{contract.custom.source}}']],
        ]]);

        self::assertSame(['seuil_km', 'source', 'tarif_km', 'titre'], $this->sorted($keys));
    }

    public function testTheSameFieldTwiceIsAskedForOnce(): void
    {
        $keys = $this->scanner->keysIn(['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => '{{contract.custom.acompte}}']],
            ['type' => 'paragraph', 'data' => ['text' => 'Rappel : {{contract.custom.acompte}}']],
        ]]);

        self::assertSame(['acompte'], $keys);
    }

    /** A catalogue variable is not a blank: the module fills it itself. */
    public function testCatalogueVariablesAreNotCustomFields(): void
    {
        $keys = $this->scanner->keysIn(['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => '{{customer.legal_name}} paie {{contract.amount}}.']],
        ]]);

        self::assertSame([], $keys);
    }

    /**
     * A key the factory would refuse is a key that can never be filled, so it
     * must not become a requirement either. Uppercase, dots and dashes are out.
     */
    public function testAKeyThatCouldNeverBeFilledIsNotAskedFor(): void
    {
        $keys = $this->scanner->keysIn(['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => '{{contract.custom.MaCle}} {{contract.custom.ma-cle}} {{contract.custom.ma.cle}}']],
        ]]);

        self::assertSame([], $keys);
    }

    public function testAnEmptyDocumentAsksForNothing(): void
    {
        self::assertSame([], $this->scanner->keysIn([]));
        self::assertSame([], $this->scanner->keysIn(['blocks' => []]));
        self::assertSame([], $this->scanner->keysIn(['blocks' => 'not an array']));
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function sorted(array $keys): array
    {
        sort($keys);

        return $keys;
    }
}
