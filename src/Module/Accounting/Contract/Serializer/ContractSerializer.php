<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Serializer;

use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionInterface;
use Aurora\Module\Accounting\Contract\Service\ContractSeal;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use const DATE_ATOM;

#[AsAlias(ContractSerializerInterface::class)]
class ContractSerializer implements ContractSerializerInterface
{
    public function __construct(protected readonly ContractSeal $seal) {}

    /** @return array<string, mixed> */
    public function serialize(ContractInterface $contract): array
    {
        $customer = $contract->getCustomer();

        return [
            'id' => $contract->getId(),
            'reference' => $contract->getReference(),
            'status' => $contract->getStatus()->value,
            'statusLabel' => $contract->getStatus()->getLabel(),
            'isFrozen' => $contract->isFrozen(),
            'isEditable' => $contract->getStatus()->isEditable() && !$contract->isFrozen(),
            'locale' => $contract->getLocale(),
            'customerId' => $customer->getId(),
            'customerName' => $customer->getLegalName(),
            'amountCents' => $contract->getAmountCents(),
            'amountCurrency' => $contract->getAmountCurrency()?->value,
            'effectiveDate' => $contract->getEffectiveDate()?->format('Y-m-d'),
            'frozenAt' => $contract->getFrozenAt()?->format(DATE_ATOM),
            'createdAt' => $contract->getCreatedAt()->format(DATE_ATOM),
            'body' => $this->part($contract->getBodyVersion()),
            'annex' => $this->part($contract->getAnnexVersion()),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDocument(ContractInterface $contract): array
    {
        return [
            ...$this->serialize($contract),
            'renderedHtml' => $contract->getRenderedHtml(),
            // The seal, as a block a human can read and check. A hash shown
            // without its algorithm and its canonical form is a string nobody
            // can do anything with.
            'seal' => [
                'contentHash' => $contract->getContentHash(),
                'hashAlgo' => $contract->getHashAlgo(),
                'canonicalVersion' => $contract->getCanonicalVersion(),
                'frozenAt' => $contract->getFrozenAt()?->format(DATE_ATOM),
                // Recomputed on every view rather than trusted. This page is
                // where somebody would look after suspecting something, so the
                // answer has to be current, not stored.
                'verified' => $contract->isFrozen() && $this->seal->verify($contract),
            ],
        ];
    }

    /**
     * A pinned version, and whether the template has moved on since.
     *
     * @return array<string, mixed>|null
     */
    private function part(?ContractTemplateVersionInterface $version): ?array
    {
        if (!$version instanceof ContractTemplateVersionInterface) {
            return null;
        }

        $template = $version->getTemplate();
        $latest = $template->getLatestPublishedVersion();

        return [
            'templateId' => $template->getId(),
            'templateName' => $template->getName(),
            'versionId' => $version->getId(),
            'versionNumber' => $version->getNumber(),
            // Said out loud, because a draft pinned to version 2 while version
            // 3 is in force is not wrong - it is a choice somebody should be
            // able to see and redo.
            'latestVersionNumber' => $latest?->getNumber(),
            'isOutdated' => $latest instanceof ContractTemplateVersionInterface && $latest->getNumber() > $version->getNumber(),
        ];
    }
}
