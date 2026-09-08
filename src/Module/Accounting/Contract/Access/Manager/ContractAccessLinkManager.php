<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Access\Manager;

use Aurora\Core\Mail\Service\MailService;
use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Access\Entity\AbstractContractAccessLink;
use Aurora\Module\Accounting\Contract\Access\Entity\ContractAccessLink;
use Aurora\Module\Accounting\Contract\Access\Entity\ContractAccessLinkInterface;
use Aurora\Module\Accounting\Contract\Access\Repository\ContractAccessLinkRepository;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Enum\ContractStatusEnum;
use Aurora\Module\Dev\Audit\Service\AuditLogger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function hash_equals;

/**
 * The addresses that open a contract from outside.
 *
 * Three rules hold this together, and none of them is left to a caller:
 *
 * 1. **The secret exists once.** `create()` returns the URL because that is the
 *    only moment the plaintext token is available; nothing can recover it
 *    afterwards, which is the whole point of storing a hash.
 * 2. **Only a sealed contract gets a link.** A draft can still change, and an
 *    address that opens a moving document is the failure the entire snapshot
 *    design exists to prevent.
 * 3. **One live link at a time.** Minting a second revokes the first, so
 *    "which address is valid" always has one answer - and revoking is what a
 *    resend has to mean, not an extra door left open.
 */
#[AsAlias(ContractAccessLinkManagerInterface::class)]
class ContractAccessLinkManager implements ContractAccessLinkManagerInterface
{
    /**
     * How long an address stays valid, in days.
     *
     * Thirty, because an offer that can still be accepted a year later is a
     * liability and the paper version always carried a validity period. A
     * setting can come the day somebody wants a different number; a constant
     * that is documented beats a column nobody has a use for yet.
     */
    public const int DEFAULT_LIFETIME_DAYS = 30;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly AuditLogger $auditLogger,
        protected readonly ContractAccessLinkRepository $links,
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly MailService $mail,
        protected readonly TranslatorInterface $translator,
    ) {}

    public function send(ContractInterface $contract): ContractAccessLinkInterface
    {
        if (!$contract->isFrozen()) {
            throw new FieldException('status', $this->translator->trans('backend.accounting.contracts.errors.seal_before_sending'));
        }

        if ($contract->getStatus()->isEngaged()) {
            // Somebody has signed. Re-sending would hand out a fresh address to
            // a document that is already committed, which is not a resend but a
            // second chance to sign the same thing.
            throw new FieldException('status', $this->translator->trans('backend.accounting.contracts.errors.already_engaged'));
        }

        $link = $this->createLink();
        $token = $link->mint();

        $link
            ->setContract($contract)
            ->setRecipientEmail($contract->getCustomer()->getContractualEmail())
            ->setExpiresAt(new DateTimeImmutable(sprintf('+%d days', self::DEFAULT_LIFETIME_DAYS)));

        // Any address handed out before is revoked. A resend replaces, it does
        // not add: two live links means two answers to "is this address valid".
        foreach ($this->links->findForContract($contract) as $previous) {
            if (!$previous->isRevoked()) {
                $previous->revoke(new DateTimeImmutable());
            }
        }

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $url = $this->urlFor($link, $token);

        $this->mail->send(
            to: $link->getRecipientEmail(),
            subjectKey: 'accounting.email.contract_link.subject',
            template: '@Accounting/email/contract_link.html.twig',
            context: [
                'contract' => $contract,
                'customer' => $contract->getCustomer(),
                // The address, and nothing of the document itself. A mailbox is
                // not where a contract should be readable, and a forwarded mail
                // should carry a door rather than the room behind it.
                'url' => $url,
                'expiresAt' => $link->getExpiresAt(),
            ],
            locale: $contract->getLocale(),
        );

        $link->markSent(new DateTimeImmutable());
        $contract->setStatus(ContractStatusEnum::Sent);
        $this->entityManager->flush();

        $this->auditLogger->log('accounting', 'contract.link_sent', 'Contract', $contract->getId(), [
            'reference' => $contract->getReference(),
            'recipient' => $link->getRecipientEmail(),
            'selector' => $link->getSelector(),
            'expiresAt' => $link->getExpiresAt()->format(DATE_ATOM),
        ]);

        return $link;
    }

    public function revoke(ContractAccessLinkInterface $link): void
    {
        $link->revoke(new DateTimeImmutable());
        $this->entityManager->flush();

        $this->auditLogger->log('accounting', 'contract.link_revoked', 'Contract', $link->getContract()->getId(), [
            'reference' => $link->getContract()->getReference(),
            'selector' => $link->getSelector(),
        ]);
    }

    /**
     * The link a selector and a secret name, or null.
     *
     * Null for every reason: unknown selector, wrong secret, revoked, expired.
     * The caller renders one page for all of them, because telling a stranger
     * which of those it was tells them which guesses landed.
     */
    public function resolveUsable(string $selector, string $token): ?ContractAccessLinkInterface
    {
        $link = $this->links->findBySelector($selector);

        if (!$link instanceof ContractAccessLinkInterface) {
            return null;
        }

        // Constant time, on the hash rather than the secret: a comparison that
        // returns early on the first wrong character tells somebody how much of
        // it they have right.
        if (!hash_equals($link->getHashedToken(), AbstractContractAccessLink::hashToken($token))) {
            return null;
        }

        if (!$link->isUsable(new DateTimeImmutable())) {
            return null;
        }

        return $link;
    }

    /**
     * Records that the document was opened.
     *
     * The first open moves the contract from sent to opened, which is the one
     * thing the back office can say about a link without asking the customer.
     */
    public function markOpened(ContractAccessLinkInterface $link): void
    {
        $wasNeverOpened = !$link->getFirstOpenedAt() instanceof DateTimeImmutable;
        $link->markUsed(new DateTimeImmutable());

        $contract = $link->getContract();

        if ($wasNeverOpened && ContractStatusEnum::Sent === $contract->getStatus()) {
            $contract->setStatus(ContractStatusEnum::Opened);
        }

        $this->entityManager->flush();
    }

    /**
     * The absolute address that opens this contract.
     *
     * Absolute because it goes in an email, and built by the router rather than
     * concatenated: a path written by hand has no way of noticing when the
     * route moves.
     */
    public function urlFor(ContractAccessLinkInterface $link, string $token): string
    {
        return $this->urlGenerator->generate(
            'public_contract_show',
            ['selector' => $link->getSelector(), 'token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    protected function createLink(): ContractAccessLinkInterface
    {
        return new ContractAccessLink();
    }
}
