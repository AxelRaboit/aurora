<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Signature\Dto;

interface ContractSignatureInputInterface
{
    public function getFirstName(): string;

    public function getLastName(): string;

    public function getEmail(): string;

    public function getPlace(): string;

    public function getDate(): string;

    public function getSignatureImage(): string;

    public function hasConsented(): bool;

    public function getCode(): string;
}
