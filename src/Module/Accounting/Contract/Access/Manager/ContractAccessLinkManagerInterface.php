<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Access\Manager;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;

interface ContractAccessLinkManagerInterface
{
    /**
     * Mints an address, revokes any earlier one, and mails it.
     *
     * @throws FieldException when the contract is not sealed, or already signed
     */
    public function send(ContractInterface $contract): ContractAccessLinkInterface;

    public function revoke(ContractAccessLinkInterface $link): void;

    /** The link a selector and a secret name, or null for every kind of failure. */
    public function resolveUsable(string $selector, string $token): ?ContractAccessLinkInterface;

    public function markOpened(ContractAccessLinkInterface $link): void;

    public function urlFor(ContractAccessLinkInterface $link, string $token): string;
}
