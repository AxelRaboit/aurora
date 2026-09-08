<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Signature\Manager;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Signature\Dto\ContractSignatureInputInterface;
use Aurora\Module\Accounting\Contract\Signature\Entity\ContractSignatureInterface;
use Aurora\Module\Platform\User\Entity\CoreUserInterface;
use Symfony\Component\HttpFoundation\Request;

interface ContractSignatureManagerInterface
{
    /**
     * Records the customer's signature, consuming their code.
     *
     * The payload must already have been validated: this consumes a credential,
     * and spending it on a form error would send the signer back to their
     * mailbox for nothing.
     *
     * @throws FieldException when the contract cannot be signed, or the code fails
     */
    public function signAsCustomer(
        ContractAccessLinkInterface $link,
        ContractSignatureInputInterface $input,
        Request $request,
    ): ContractSignatureInterface;

    /**
     * Records the provider's signature, which concludes the contract.
     *
     * @throws FieldException when the customer has not signed yet, or the contract is closed
     */
    public function countersign(
        ContractInterface $contract,
        ContractSignatureInputInterface $input,
        CoreUserInterface $user,
        Request $request,
    ): ContractSignatureInterface;
}
