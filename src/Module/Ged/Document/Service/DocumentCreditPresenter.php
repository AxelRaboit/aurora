<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Service;

use Aurora\Module\Ged\Document\Entity\DocumentInterface;

/**
 * The line of credit a picture has to carry when it is not ours.
 *
 * Stock photo providers make attribution a condition of use rather than a
 * courtesy: an Unsplash image displayed without its photographer's name is a
 * breach of the terms the key was issued under. So every view builder that
 * renders a document asks this, and gets null for the ordinary case - a file
 * we host owes nobody a credit.
 *
 * Kept out of DocumentUrlGenerator on purpose. That class answers "where is
 * this picture"; this one answers "whose is it", and a template that shows a
 * caption needs the second without the first.
 */
final readonly class DocumentCreditPresenter
{
    /**
     * @return array{name: string, url: string|null}|null
     */
    public function present(?DocumentInterface $document): ?array
    {
        if (!$document instanceof DocumentInterface || !$document->isRemote()) {
            return null;
        }

        $name = mb_trim((string) $document->getAttributionName());
        if ('' === $name) {
            return null;
        }

        return [
            'name' => $name,
            'url' => $document->getAttributionUrl(),
        ];
    }
}
