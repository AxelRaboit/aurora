<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Manager;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Exception\FrozenContractIsImmutableException;

interface ContractManagerInterface
{
    public function create(ContractInterface $draft): ContractInterface;

    /** @throws FrozenContractIsImmutableException */
    public function update(ContractInterface $contract): void;

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
