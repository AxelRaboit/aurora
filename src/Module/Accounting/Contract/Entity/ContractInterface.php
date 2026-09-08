<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Entity;

use Aurora\Core\Money\Enum\CurrencyEnum;
use Aurora\Core\Timestampable\TimestampableInterface;
use Aurora\Module\Accounting\Contract\Enum\ContractStatusEnum;
use Aurora\Module\Accounting\Contract\Exception\FrozenContractIsImmutableException;
use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;
use DateTimeImmutable;

interface ContractInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getReference(): ?string;

    public function setReference(?string $reference): static;

    public function getCustomer(): CustomerInterface;

    public function setCustomer(CustomerInterface $customer): static;

    public function getBodyVersion(): ?ContractTemplateVersionInterface;

    public function setBodyVersion(?ContractTemplateVersionInterface $bodyVersion): static;

    public function getAnnexVersion(): ?ContractTemplateVersionInterface;

    public function setAnnexVersion(?ContractTemplateVersionInterface $annexVersion): static;

    public function getLocale(): string;

    public function setLocale(string $locale): static;

    public function getStatus(): ContractStatusEnum;

    public function setStatus(ContractStatusEnum $status): static;

    /** @return array<string, string> */
    public function getVariables(): array;

    /** @param array<string, string> $variables */
    public function setVariables(array $variables): static;

    public function getAmountCents(): ?int;

    public function setAmountCents(?int $amountCents): static;

    public function getAmountCurrency(): ?CurrencyEnum;

    public function setAmountCurrency(?CurrencyEnum $amountCurrency): static;

    public function getEffectiveDate(): ?DateTimeImmutable;

    public function setEffectiveDate(?DateTimeImmutable $effectiveDate): static;

    public function getFrozenAt(): ?DateTimeImmutable;

    public function isFrozen(): bool;

    /** @return array<string, mixed> */
    public function getContentSnapshot(): array;

    public function getRenderedHtml(): ?string;

    public function getContentHash(): ?string;

    public function getHashAlgo(): ?string;

    public function getCanonicalVersion(): ?int;

    /**
     * Seals the document, once.
     *
     * @param array<string, mixed> $snapshot
     *
     * @throws FrozenContractIsImmutableException when already frozen
     */
    public function freeze(
        DateTimeImmutable $at,
        string $reference,
        array $snapshot,
        string $renderedHtml,
        string $contentHash,
        string $hashAlgo,
        int $canonicalVersion,
    ): static;

    /** @throws FrozenContractIsImmutableException */
    public function assertEditable(): void;
}
