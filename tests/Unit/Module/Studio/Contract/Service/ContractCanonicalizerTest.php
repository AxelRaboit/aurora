<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Studio\Contract\Service;

use Aurora\Module\Studio\Contract\Service\ContractCanonicalizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The canonical form, which is what makes the hash mean anything.
 *
 * Every test here is about one property: two encodings of the same document
 * must produce the same bytes, and two different documents must not. Without
 * the first, a verification cries tampering over a reordered array and
 * everybody learns to ignore it - an alarm nobody trusts being worse than no
 * alarm. Without the second, it never cries at all.
 */
final class ContractCanonicalizerTest extends TestCase
{
    private ContractCanonicalizer $canonicalizer;

    protected function setUp(): void
    {
        $this->canonicalizer = new ContractCanonicalizer();
    }

    public function testKeyOrderDoesNotChangeTheHash(): void
    {
        $first = ['locale' => 'fr', 'reference' => 'CM-2026-0001', 'values' => ['b' => '2', 'a' => '1']];
        $second = ['values' => ['a' => '1', 'b' => '2'], 'reference' => 'CM-2026-0001', 'locale' => 'fr'];

        self::assertSame(
            $this->canonicalizer->hash($first),
            $this->canonicalizer->hash($second),
        );
    }

    /**
     * The one thing sorting must never touch.
     *
     * Articles are a list, and their order IS the document. A canonicaliser
     * that sorted them would report two different contracts as identical.
     */
    public function testListOrderDoesChangeTheHash(): void
    {
        $first = ['parts' => [['title' => 'Article 1'], ['title' => 'Article 2']]];
        $second = ['parts' => [['title' => 'Article 2'], ['title' => 'Article 1']]];

        self::assertNotSame(
            $this->canonicalizer->hash($first),
            $this->canonicalizer->hash($second),
        );
    }

    public function testReflowedWhitespaceDoesNotChangeTheHash(): void
    {
        $tight = ['html' => '<p>Le Client exerce une activité de boulangerie.</p>'];
        $reflowed = ['html' => "<p>Le Client   exerce\n  une activité de boulangerie.</p>  "];

        self::assertSame(
            $this->canonicalizer->hash($tight),
            $this->canonicalizer->hash($reflowed),
        );
    }

    /**
     * A non-breaking space is typography a contract means.
     *
     * "10 000 €" with a narrow no-break space and "10 000 €" with an ordinary
     * one are not the same document, and the reader was shown one of them.
     */
    public function testANonBreakingSpaceIsNotCollapsedAway(): void
    {
        $nbsp = ['html' => "10\u{00A0}000 €"];
        $plain = ['html' => '10 000 €'];

        self::assertNotSame(
            $this->canonicalizer->hash($nbsp),
            $this->canonicalizer->hash($plain),
        );
    }

    public function testAccentsEncodeOneWayOnly(): void
    {
        $json = $this->canonicalizer->canonicalize(['title' => 'CONTRAT DE PRESTATION']);

        self::assertStringNotContainsString('\\u', $json);
    }

    public function testSlashesAreNotEscaped(): void
    {
        $json = $this->canonicalizer->canonicalize(['date' => '01/10/2026']);

        self::assertStringContainsString('01/10/2026', $json);
    }

    public function testChangingOneCharacterChangesTheHash(): void
    {
        $before = ['html' => '<p>Le forfait mensuel est de 850 €.</p>'];
        $after = ['html' => '<p>Le forfait mensuel est de 950 €.</p>'];

        self::assertNotSame(
            $this->canonicalizer->hash($before),
            $this->canonicalizer->hash($after),
        );
    }

    public function testAMatchingDocumentVerifies(): void
    {
        $document = ['html' => '<p>Article 1</p>', 'locale' => 'fr'];
        $hash = $this->canonicalizer->hash($document);

        self::assertTrue(
            $this->canonicalizer->matches($document, $hash, ContractCanonicalizer::VERSION),
        );
    }

    public function testAnAlteredDocumentDoesNotVerify(): void
    {
        $document = ['html' => '<p>Article 1</p>'];
        $hash = $this->canonicalizer->hash($document);

        self::assertFalse(
            $this->canonicalizer->matches(['html' => '<p>Article 2</p>'], $hash, ContractCanonicalizer::VERSION),
        );
    }

    /**
     * Unverifiable and altered are different answers.
     *
     * A contract sealed under a rule this code no longer implements has not
     * been tampered with; saying so would be the fastest way to make the
     * verification worthless.
     */
    public function testAnUnknownCanonicalVersionIsRefusedRatherThanFailed(): void
    {
        $document = ['html' => '<p>Article 1</p>'];
        $hash = $this->canonicalizer->hash($document);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sealed under canonical form');

        $this->canonicalizer->matches($document, $hash, ContractCanonicalizer::VERSION + 1);
    }

    public function testTheHashIsSha256Hex(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $this->canonicalizer->hash(['html' => 'x']),
        );
    }
}
