<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Refusal\Manager;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Studio\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Studio\Contract\Refusal\Dto\ContractRefusalInputInterface;
use Symfony\Component\HttpFoundation\Request;

interface ContractRefusalManagerInterface
{
    /**
     * Records the customer's refusal and closes the address it came through.
     *
     * @throws FieldException when the contract was never sealed, is already
     *                        engaged, or has already been refused
     */
    public function refuseAsCustomer(
        ContractAccessLinkInterface $link,
        ContractRefusalInputInterface $input,
        Request $request,
    ): void;
}
