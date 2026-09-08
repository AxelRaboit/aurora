<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ContractTemplateVersionInput implements ContractTemplateVersionInputInterface
{
    /**
     * @param array<string, array{title: string, content: array<string, mixed>}> $translations
     */
    public function __construct(
        // At least one language, because a version with no wording is not a
        // draft of anything. Which languages are expected is the locale
        // layer's business, not this DTO's.
        #[Assert\Count(min: 1, minMessage: 'backend.accounting.contract_templates.errors.translations_required')]
        public readonly array $translations = [],
    ) {}

    public function getTranslations(): array
    {
        return $this->translations;
    }
}
