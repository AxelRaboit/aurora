<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Service;

use JsonException;
use RuntimeException;

use function array_is_list;
use function hash;
use function hash_equals;
use function is_array;
use function is_string;
use function json_encode;
use function ksort;
use function mb_trim;
use function preg_replace;
use function sprintf;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Turns a document into the exact bytes its hash is taken over.
 *
 * A hash without a defined canonical form proves nothing, and that is the
 * failure this class exists to prevent. Two encodings of the same document -
 * keys in a different order, a unicode escape here and a literal accent there,
 * a trailing space - produce two different hashes, so a verification would
 * report tampering where there was none, and everybody would learn to ignore
 * it. An alarm nobody trusts is worse than no alarm.
 *
 * Four rules, and they are the specification of `VERSION`:
 *
 * 1. **Object keys are sorted**, recursively. Insertion order is an accident of
 *    how the array was built.
 * 2. **Lists keep their order.** The order of articles is the document.
 * 3. **No escaping of slashes or unicode.** The same accented word must not
 *    encode two ways depending on the flags a caller passed.
 * 4. **Breaking whitespace in strings is normalised**: runs of it collapse to
 *    one space, and the ends are trimmed. HTML treats them as equivalent, so a
 *    reflowed template that renders identically must hash identically. The
 *    non-breaking spaces are NOT touched - they are typography a contract
 *    means.
 *
 * `VERSION` is stored on every contract next to its hash. When a rule has to
 * change, old contracts keep verifying under the rule they were sealed with
 * instead of all turning red at once.
 */
final readonly class ContractCanonicalizer
{
    /**
     * The canonical form in force.
     *
     * Bump this only when a rule above changes, never when the document
     * structure grows a field: a new key sorts into place on its own.
     */
    public const int VERSION = 1;

    public const string ALGO = 'sha256';

    /**
     * The bytes a hash is taken over.
     *
     * @param array<string, mixed> $document
     */
    public function canonicalize(array $document): string
    {
        try {
            $json = json_encode(
                $this->normalize($document),
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                // A float that happens to be whole must not silently become an
                // int in the encoding; the hash would then depend on the value
                // rather than only on the structure.
                | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $jsonException) {
            throw new RuntimeException('The document cannot be canonicalised, so it cannot be sealed: '.$jsonException->getMessage(), $jsonException->getCode(), previous: $jsonException);
        }

        return $json;
    }

    /** @param array<string, mixed> $document */
    public function hash(array $document): string
    {
        return hash(self::ALGO, $this->canonicalize($document));
    }

    /**
     * Whether a stored hash still matches the document it was taken over.
     *
     * The stored canonical version decides which rule applies. A contract
     * sealed under a version this code no longer implements is reported as
     * unverifiable rather than as altered, which are very different answers.
     */
    public function matches(array $document, string $expectedHash, int $canonicalVersion): bool
    {
        if (self::VERSION !== $canonicalVersion) {
            throw new RuntimeException(sprintf('This contract was sealed under canonical form %d and this code implements %d. Verify it with the code that sealed it.', $canonicalVersion, self::VERSION));
        }

        return hash_equals($expectedHash, $this->hash($document));
    }

    /**
     * Sorts every object, leaves every list, normalises every string.
     *
     * `array_is_list` is what tells the two apart, and it has to: sorting a
     * list would reorder the articles of a contract.
     */
    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->normalizeWhitespace($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->normalize(...), $value);
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item);
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * One space for any run of breaking whitespace, and nothing at the ends.
     *
     * The class is spelled out rather than written `\p{Zs}`, because that
     * property includes the non-breaking space and the narrow one - and those
     * are deliberate typography in a contract ("10 000 €", "art. 5", a French
     * space before a colon). Collapsing them would change what the document
     * says it says, and worse, would make a document hash differently from the
     * bytes a reader was shown.
     */
    private function normalizeWhitespace(string $value): string
    {
        return mb_trim((string) preg_replace(
            '/[ \t\r\n\f\v\x{1680}\x{2000}-\x{200A}\x{205F}\x{3000}]+/u',
            ' ',
            $value,
        ));
    }
}
