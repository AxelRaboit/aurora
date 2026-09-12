<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Access\Entity;

use Aurora\Module\Studio\Contract\Entity\ContractInterface;
use DateTimeImmutable;

interface ContractAccessLinkInterface
{
    public function getId(): ?int;

    /** Mints both halves and returns the secret, once. */
    public function mint(): string;

    public function getSelector(): string;

    public function getHashedToken(): string;

    /** The secret, only within the request that minted it. */
    public function getPlainToken(): ?string;

    public function getContract(): ContractInterface;

    public function setContract(ContractInterface $contract): static;

    public function getRecipientEmail(): string;

    public function setRecipientEmail(string $recipientEmail): static;

    public function getExpiresAt(): DateTimeImmutable;

    public function setExpiresAt(DateTimeImmutable $expiresAt): static;

    public function getRevokedAt(): ?DateTimeImmutable;

    public function revoke(DateTimeImmutable $at): static;

    public function getSentAt(): ?DateTimeImmutable;

    public function markSent(DateTimeImmutable $at): static;

    public function getFirstOpenedAt(): ?DateTimeImmutable;

    public function getLastUsedAt(): ?DateTimeImmutable;

    public function markUsed(DateTimeImmutable $at): static;

    public function getCreatedAt(): DateTimeImmutable;

    public function isRevoked(): bool;

    public function isExpired(DateTimeImmutable $now): bool;

    public function isUsable(DateTimeImmutable $now): bool;
}
