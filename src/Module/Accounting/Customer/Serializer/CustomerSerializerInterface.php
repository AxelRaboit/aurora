<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Customer\Serializer;

use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;

interface CustomerSerializerInterface
{
    /** @return array<string, mixed> */
    public function serialize(CustomerInterface $customer): array;
}
