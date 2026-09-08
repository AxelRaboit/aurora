<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Entity;

interface ContractTemplateVersionTranslationInterface
{
    public function getId(): ?int;

    public function getVersion(): ContractTemplateVersionInterface;

    public function setVersion(ContractTemplateVersionInterface $version): static;

    public function getLocale(): string;

    public function setLocale(string $locale): static;

    public function getTitle(): string;

    public function setTitle(string $title): static;

    /** @return array<string, mixed> */
    public function getContent(): array;

    /** @param array<string, mixed> $content */
    public function setContent(array $content): static;
}
