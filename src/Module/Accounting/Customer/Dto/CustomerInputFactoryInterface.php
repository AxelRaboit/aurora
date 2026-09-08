<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Customer\Dto;

interface CustomerInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): CustomerInputInterface;
}
