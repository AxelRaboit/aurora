<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Serializer;

use Aurora\Module\Accounting\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Accounting\Contract\Access\Repository\ContractAccessLinkRepository;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionInterface;
use Aurora\Module\Accounting\Contract\Service\ContractSeal;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use const DATE_ATOM;

#[AsAlias(ContractSerializerInterface::class)]
class ContractSerializer implements ContractSerializerInterface
{
    public function __construct(
        protected readonly ContractSeal $seal,
        protected readonly ContractAccessLinkRepository $links,
    ) {}

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
            'link' => $this->link($contract),
            'hasPdf' => $contract->hasPdf(),
            'pdfHash' => $contract->getPdfHash(),
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
     * What the back office can say about the address that was handed out.
     *
     * Never the secret. It exists for one request, the one that minted it, and
     * by the time anything is serialized it is gone - which is the whole point
     * of storing a hash. A reader who needs to reach the document opens it from
     * here, not from the customer's link.
     *
     * @return array<string, mixed>|null
     */
    private function link(ContractInterface $contract): ?array
    {
        $link = $this->links->findActiveFor($contract);

        if (!$link instanceof ContractAccessLinkInterface) {
            return null;
        }

        return [
            'recipientEmail' => $link->getRecipientEmail(),
            'sentAt' => $link->getSentAt()?->format(DATE_ATOM),
            'expiresAt' => $link->getExpiresAt()->format(DATE_ATOM),
            // The one thing a link answers that nothing else can: whether the
            // customer ever opened the document.
            'firstOpenedAt' => $link->getFirstOpenedAt()?->format(DATE_ATOM),
            'lastUsedAt' => $link->getLastUsedAt()?->format(DATE_ATOM),
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
