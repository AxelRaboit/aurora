<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Exception;

use RuntimeException;

use function sprintf;

/**
 * A block the contract renderer cannot turn into HTML.
 *
 * Thrown rather than skipped, which is the opposite of what a web page should
 * do. Skipping is right when the cost of a missing block is one absent widget;
 * here the cost is a clause that quietly disappears from a document somebody
 * signs, and a contract that refuses to freeze is a problem somebody fixes
 * while a contract missing an article is one nobody notices.
 *
 * Reaching this means a template carries a block type the contract renderer
 * does not support - an image, an embed, a callout. The fix is either to take
 * it out of the wording or to teach the renderer about it, and both are
 * decisions rather than accidents.
 */
final class UnrenderableBlockException extends RuntimeException
{
    public static function unknownType(string $type, int $index): self
    {
        return new self(sprintf(
            'Block %d is of type "%s", which a contract cannot render. Remove it from the wording, or teach ContractDocumentRenderer about it.',
            $index + 1,
            '' === $type ? 'unnamed' : $type,
        ));
    }

    public static function malformed(int $index): self
    {
        return new self(sprintf('Block %d is not a block at all. The stored document is malformed.', $index + 1));
    }
}
