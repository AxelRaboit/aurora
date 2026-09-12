<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Customer\Manager;

use Aurora\Module\Studio\Customer\Dto\CustomerInputInterface;
use Aurora\Module\Studio\Customer\Entity\CustomerInterface;

interface CustomerManagerInterface
{
    public function create(CustomerInputInterface $input): CustomerInterface;

    public function update(CustomerInterface $customer, CustomerInputInterface $input): void;

    public function delete(CustomerInterface $customer): void;
}
