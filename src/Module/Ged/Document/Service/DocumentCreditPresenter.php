<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Service;

use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnum;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Ged\Document\Entity\DocumentInterface;

/**
 * The line of credit a picture has to carry when it is not ours.
 *
 * Stock photo providers make attribution a condition of use rather than a
 * courtesy: a Pexels image displayed without its photographer's name is a
 * breach of the guidelines the key was issued under. So every view builder
 * that renders a document asks this, and gets null for the ordinary case - a
 * file we host owes nobody a credit.
 *
 * Kept out of DocumentUrlGenerator on purpose. That class answers "where is
 * this picture"; this one answers "whose is it", and a template that shows a
 * caption needs the second without the first.
 *
 * A site may switch the line off, and the switch lives here rather than in the
 * four templates that render it: hiding it in one place and forgetting the
 * other three is how a page ends up half-credited. On by default, because that
 * is what the Pexels API guidelines ask of the key the pictures came through -
 * turning it off is a decision the site owner takes about their own account.
 */
final readonly class DocumentCreditPresenter
{
    public function __construct(
        private SettingRepository $settingRepository,
    ) {}

    /**
     * @return array{name: string, url: string|null}|null
     */
    public function present(?DocumentInterface $document): ?array
    {
        if (!$this->settingRepository->getBoolean(ApplicationParameterEnum::MediaCreditVisible->value, true)) {
            return null;
        }

        // Keyed on the attribution itself rather than on where the file is
        // stored. We host every picture now, including the stock ones, so
        // "is this ours" no longer distinguishes anything - "does this name
        // someone" does, and it is the question the credit answers.
        $name = mb_trim((string) $document?->getAttributionName());
        if ('' === $name) {
            return null;
        }

        return [
            'name' => $name,
            'url' => $document?->getAttributionUrl(),
        ];
    }
}
