<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Entity;

use Aurora\Core\Timestampable\TimestampableInterface;
use Aurora\Module\Studio\Contract\Exception\PublishedVersionIsImmutableException;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;

interface ContractTemplateVersionInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getTemplate(): ContractTemplateInterface;

    public function setTemplate(ContractTemplateInterface $template): static;

    public function getNumber(): int;

    public function setNumber(int $number): static;

    public function getPublishedAt(): ?DateTimeImmutable;

    public function isPublished(): bool;

    /**
     * Closes the version for good.
     *
     * @throws PublishedVersionIsImmutableException when already published
     */
    public function publish(DateTimeImmutable $at): static;

    /**
     * Refuses every write once published.
     *
     * @throws PublishedVersionIsImmutableException
     */
    public function assertEditable(): void;

    public function getGoverningLocale(): ?string;

    /**
     * Names the language that prevails between translations of this wording.
     *
     * Null means the version carries one language and has nothing to arbitrate.
     */
    public function setGoverningLocale(?string $locale): static;

    /** @return Collection<string, ContractTemplateVersionTranslationInterface> */
    public function getTranslations(): Collection;

    public function getTranslation(string $locale): ?ContractTemplateVersionTranslationInterface;

    public function addTranslation(ContractTemplateVersionTranslationInterface $translation): static;

    public function removeTranslation(string $locale): static;

    /**
     * Writes an existing locale's wording.
     *
     * @param array<string, mixed> $content
     */
    public function updateTranslation(string $locale, string $title, array $content): static;
}
