<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Customer\Manager;

use Aurora\Module\Accounting\Customer\Dto\CustomerInputInterface;
use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;

interface CustomerManagerInterface
{
    public function create(CustomerInputInterface $input): CustomerInterface;

    public function update(CustomerInterface $customer, CustomerInputInterface $input): void;

    public function delete(CustomerInterface $customer): void;
}
