<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

interface ContractInputInterface
{
    public function getCustomerId(): ?int;

    public function getBodyTemplateId(): ?int;

    public function getAnnexTemplateId(): ?int;

    public function getLocale(): string;

    public function getAmountCents(): ?int;

    public function getAmountCurrency(): ?string;

    public function getEffectiveDate(): ?string;
}
