<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Serializer;

use Aurora\Module\Studio\Contract\Entity\ContractInterface;

interface ContractSerializerInterface
{
    /** @return array<string, mixed> */
    public function serialize(ContractInterface $contract): array;

    /**
     * The frozen document and its seal, for the page that shows one contract.
     *
     * Separate from the row payload: a list has no business carrying the full
     * HTML of every contract it shows.
     *
     * @return array<string, mixed>
     */
    public function serializeDocument(ContractInterface $contract): array;
}
