<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Service;

use Aurora\Module\Studio\Contract\Entity\ContractInterface;

/**
 * The one place that says what a contract's hash covers.
 *
 * It exists because sealing and verifying must compose the document
 * identically, and two places doing it is how they stop agreeing. Every hash
 * in this module goes through here, in both directions.
 *
 * What is covered is BOTH halves: the inputs (which template versions, which
 * values, which language) and the output (the HTML a person actually read).
 * Hashing only the inputs would miss a renderer that later produces different
 * text from the same inputs. Hashing only the HTML would prove what was read
 * and lose what it was built from. Neither is enough on its own, and covering
 * both costs one extra key.
 *
 * Nothing is duplicated to achieve it: the snapshot lives in its column, the
 * HTML in its own, and this composes them on demand.
 */
final readonly class ContractSeal
{
    public function __construct(private ContractCanonicalizer $canonicalizer) {}

    /**
     * The document a hash is taken over, composed from what is stored.
     *
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    public function document(array $snapshot, string $renderedHtml): array
    {
        return [
            'snapshot' => $snapshot,
            'renderedHtml' => $renderedHtml,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot, string $renderedHtml): string
    {
        return $this->canonicalizer->hash($this->document($snapshot, $renderedHtml));
    }

    /**
     * Whether a frozen contract still matches its own hash.
     *
     * False means the stored document changed after it was sealed. That is the
     * one answer this module treats as an incident rather than as an error.
     */
    public function verify(ContractInterface $contract): bool
    {
        $hash = $contract->getContentHash();
        $version = $contract->getCanonicalVersion();

        if (null === $hash || null === $version) {
            return false;
        }

        return $this->canonicalizer->matches(
            $this->document($contract->getContentSnapshot(), $contract->getRenderedHtml() ?? ''),
            $hash,
            $version,
        );
    }
}
