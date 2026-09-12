<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Signature\Manager;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Platform\User\Entity\CoreUserInterface;
use Aurora\Module\Studio\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Studio\Contract\Entity\ContractInterface;
use Aurora\Module\Studio\Contract\Signature\Dto\ContractSignatureInputInterface;
use Aurora\Module\Studio\Contract\Signature\Entity\ContractSignatureInterface;
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
