<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Manager;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Dto\ContractInputInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Exception\FrozenContractIsImmutableException;

interface ContractManagerInterface
{
    /** @throws FieldException when a choice names a row that cannot be used */
    public function create(ContractInputInterface $input): ContractInterface;

    /**
     * @throws FrozenContractIsImmutableException
     * @throws FieldException
     */
    public function update(ContractInterface $contract, ContractInputInterface $input): void;

    /** @throws FrozenContractIsImmutableException */
    public function delete(ContractInterface $contract): void;

    /**
     * Seals the document: reference, snapshot, rendered HTML and hash, in one
     * call, before the link goes out.
     *
     * @throws FrozenContractIsImmutableException when already frozen
     * @throws FieldException                     when the wording cannot produce a document
     */
    public function freeze(ContractInterface $contract): void;
}
