<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Signature\Entity;

use Aurora\Module\Studio\Contract\Access\Entity\ContractAccessLinkInterface;
use DateTimeImmutable;

interface ContractSignatureChallengeInterface
{
    public function getId(): ?int;

    /** Mints a code and returns it, once. */
    public function mint(): string;

    /** The six digits, only within the request that minted them. */
    public function getPlainCode(): ?string;

    public function getLink(): ContractAccessLinkInterface;

    public function setLink(ContractAccessLinkInterface $link): static;

    public function getHashedCode(): string;

    public function getSentTo(): string;

    public function setSentTo(string $sentTo): static;

    public function getExpiresAt(): DateTimeImmutable;

    public function getAttempts(): int;

    public function recordFailedAttempt(): static;

    public function getConsumedAt(): ?DateTimeImmutable;

    public function consume(DateTimeImmutable $at): static;

    public function getCreatedAt(): DateTimeImmutable;

    public function isConsumed(): bool;

    public function isExpired(DateTimeImmutable $now): bool;

    public function hasAttemptsLeft(): bool;

    public function isUsable(DateTimeImmutable $now): bool;
}
