<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

interface ContractTemplateVersionInputInterface
{
    /** @return array<string, array{title: string, content: array<string, mixed>}> */
    public function getTranslations(): array;
}
