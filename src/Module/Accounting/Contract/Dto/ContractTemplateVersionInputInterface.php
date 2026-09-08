<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

interface ContractTemplateVersionInputInterface
{
    /** @return array<string, array{title: string, content: array<string, mixed>}> */
    public function getTranslations(): array;

    /** The language that prevails between translations, or null for a single-language version. */
    public function getGoverningLocale(): ?string;
}
