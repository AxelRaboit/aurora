<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Manager;

use Aurora\Core\Money\Enum\CurrencyEnum;
use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Core\Sequence\SequencePrefixEnum;
use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Accounting\Contract\Dto\ContractInputInterface;
use Aurora\Module\Accounting\Contract\Entity\Contract;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionTranslationInterface;
use Aurora\Module\Accounting\Contract\Enum\ContractTemplateKindEnum;
use Aurora\Module\Accounting\Contract\Exception\UnrenderableBlockException;
use Aurora\Module\Accounting\Contract\Repository\ContractTemplateRepository;
use Aurora\Module\Accounting\Contract\Service\ContractCanonicalizer;
use Aurora\Module\Accounting\Contract\Service\ContractCustomFieldScanner;
use Aurora\Module\Accounting\Contract\Service\ContractDocumentRenderer;
use Aurora\Module\Accounting\Contract\Service\ContractSeal;
use Aurora\Module\Accounting\Contract\Service\ContractVariableResolver;
use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;
use Aurora\Module\Accounting\Customer\Repository\CustomerRepository;
use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnum;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Dev\Audit\Service\AuditLogger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Contracts\Translation\TranslatorInterface;

use function implode;
use function is_array;
use function sprintf;

/**
 * Contracts, and the one moment that matters: the freeze.
 *
 * Everything before it is ordinary editing. The freeze is where a set of
 * choices becomes a document: the reference is minted, the wording is read out
 * of the template versions, the variables are substituted, the HTML is
 * rendered, and the hash is taken over both halves. All of it in one call, so
 * there is no instant at which a contract holds half a seal.
 *
 * It happens before the link goes out, never at signature. That ordering is
 * what makes every signature safe: nothing can move between the moment the
 * document is read and the moment it is signed, so no signature can ever be
 * invalidated by an edit - there is no edit to make.
 */
#[AsAlias(ContractManagerInterface::class)]
class ContractManager implements ContractManagerInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly AuditLogger $auditLogger,
        protected readonly ContractVariableResolver $variables,
        protected readonly ContractDocumentRenderer $renderer,
        protected readonly ContractCanonicalizer $canonicalizer,
        protected readonly ContractSeal $seal,
        protected readonly SequenceGenerator $sequenceGenerator,
        protected readonly SettingRepository $settingRepository,
        protected readonly CustomerRepository $customerRepository,
        protected readonly ContractTemplateRepository $templateRepository,
        protected readonly TranslatorInterface $translator,
        protected readonly ContractCustomFieldScanner $customFields,
    ) {}

    public function create(ContractInputInterface $input): ContractInterface
    {
        $contract = $this->createContract();
        $this->applyInput($contract, $input);

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        $this->auditLogger->log('accounting', 'contract.created', 'Contract', $contract->getId(), $this->auditPayload($contract));

        return $contract;
    }

    public function update(ContractInterface $contract, ContractInputInterface $input): void
    {
        $contract->assertEditable();

        $this->applyInput($contract, $input);
        $this->entityManager->flush();

        $this->auditLogger->log('accounting', 'contract.updated', 'Contract', $contract->getId(), $this->auditPayload($contract));
    }

    /**
     * Turns the choices into the rows a contract pins.
     *
     * The version is resolved here, at draft time, and not at freeze. A
     * template that publishes a new version tomorrow must not silently change
     * a contract somebody is in the middle of preparing - and the screen can
     * say "a newer version exists" precisely because the two are different.
     */
    protected function applyInput(ContractInterface $contract, ContractInputInterface $input): void
    {
        $customer = null === $input->getCustomerId()
            ? null
            : $this->customerRepository->find($input->getCustomerId());

        if (!$customer instanceof CustomerInterface) {
            throw new FieldException('customerId', $this->translator->trans('backend.accounting.contracts.errors.customer_required'));
        }

        $contract
            ->setCustomer($customer)
            ->setCustomFields($input->getCustomFields())
            ->setLocale($input->getLocale())
            ->setAmountCents($input->getAmountCents())
            ->setAmountCurrency(null === $input->getAmountCurrency() ? null : CurrencyEnum::tryFrom($input->getAmountCurrency()))
            ->setEffectiveDate($this->effectiveDate($input))
            ->setBodyVersion($this->publishedVersionOf($input->getBodyTemplateId(), ContractTemplateKindEnum::Body, 'bodyTemplateId'))
            ->setAnnexVersion($this->publishedVersionOf($input->getAnnexTemplateId(), ContractTemplateKindEnum::Annex, 'annexTemplateId'));
    }

    /**
     * The version in force of a chosen template.
     *
     * A template with nothing published is refused here rather than at freeze,
     * so the refusal lands on the picker that offered it.
     */
    protected function publishedVersionOf(?int $templateId, ContractTemplateKindEnum $kind, string $field): ?ContractTemplateVersionInterface
    {
        if (null === $templateId) {
            return null;
        }

        $template = $this->templateRepository->find($templateId);

        if (!$template instanceof ContractTemplateInterface || $template->getKind() !== $kind) {
            throw new FieldException($field, $this->translator->trans('backend.accounting.contracts.errors.template_not_found'));
        }

        $version = $template->getLatestPublishedVersion();

        if (!$version instanceof ContractTemplateVersionInterface) {
            throw new FieldException($field, $this->translator->trans('backend.accounting.contracts.errors.template_never_published', ['{template}' => $template->getName()]));
        }

        return $version;
    }

    protected function effectiveDate(ContractInputInterface $input): ?DateTimeImmutable
    {
        $raw = $input->getEffectiveDate();

        if (null === $raw) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        if (false === $date) {
            throw new FieldException('effectiveDate', $this->translator->trans('backend.accounting.contracts.errors.effective_date_invalid'));
        }

        return $date;
    }

    public function delete(ContractInterface $contract): void
    {
        // Only a draft. A frozen contract went out to somebody, and deleting
        // the record of what they were sent is not an editing operation - it is
        // the destruction of the only copy that proves what was agreed.
        $contract->assertEditable();

        $this->auditLogger->log('accounting', 'contract.deleted', 'Contract', $contract->getId(), $this->auditPayload($contract));

        $this->entityManager->remove($contract);
        $this->entityManager->flush();
    }

    public function freeze(ContractInterface $contract): void
    {
        $contract->assertEditable();

        $body = $contract->getBodyVersion();

        if (!$body instanceof ContractTemplateVersionInterface) {
            throw new FieldException('bodyVersion', $this->translator->trans('backend.accounting.contracts.errors.body_required'));
        }

        // Only published wording can be sent. A draft version is work in
        // progress by definition, and freezing one would seal a document its
        // author had not finished writing.
        $this->assertPublished($body, 'bodyVersion');

        $annex = $contract->getAnnexVersion();

        if ($annex instanceof ContractTemplateVersionInterface) {
            $this->assertPublished($annex, 'annexVersion');
        }

        // Checked before a reference is minted: a contract refused here has
        // consumed nothing, and the sequence has no gap to explain.
        $missing = $this->missingCustomFields($contract);

        if ([] !== $missing) {
            throw new FieldException('customFields', $this->translator->trans('backend.accounting.contracts.errors.custom_fields_missing', ['{fields}' => implode(', ', $missing)]));
        }

        // Minted before the rendering, because the reference is printed inside
        // the document and therefore has to be part of what the hash covers.
        $reference = $this->nextReference();
        $contract->setReference($reference);

        $values = $this->variables->resolve($contract);
        $deferred = $this->variables->deferredTokens();

        $parts = [];
        $html = '';

        foreach ($this->partsOf($contract) as $role => $version) {
            $part = $this->renderPart($role, $version, $contract->getLocale(), $values);
            $parts[] = $part['snapshot'];
            $html .= $part['html'];
        }

        // Appended after the last part, which is where a governing-language
        // clause belongs on paper too: after everything it arbitrates.
        $governingLocale = $this->governingLocaleOf($contract);

        if (null !== $governingLocale) {
            $html .= $this->governingLanguageClause($contract->getLocale(), $governingLocale);
        }

        $unknown = $this->renderer->unknownTokens($html, $values, $deferred);

        if ([] !== $unknown) {
            // Named, and refused. A token nobody will ever fill would reach the
            // signer as literal braces in the middle of a clause, and by then
            // the document is sealed.
            throw new FieldException('bodyVersion', $this->translator->trans('backend.accounting.contracts.errors.unknown_tokens', ['{tokens}' => implode(', ', $unknown)]));
        }

        $snapshot = [
            'canonicalVersion' => ContractCanonicalizer::VERSION,
            'locale' => $contract->getLocale(),
            'governingLocale' => $governingLocale,
            'reference' => $reference,
            'customerId' => $contract->getCustomer()->getId(),
            'values' => $values,
            'customFields' => $contract->getCustomFields(),
            'deferredTokens' => $deferred,
            'parts' => $parts,
        ];

        $contract->freeze(
            new DateTimeImmutable(),
            $reference,
            $snapshot,
            $html,
            $this->seal->hash($snapshot, $html),
            ContractCanonicalizer::ALGO,
            ContractCanonicalizer::VERSION,
        );

        $this->entityManager->flush();

        $this->auditLogger->log('accounting', 'contract.frozen', 'Contract', $contract->getId(), [
            ...$this->auditPayload($contract),
            'contentHash' => $contract->getContentHash(),
            'bodyVersion' => $body->getNumber(),
            'annexVersion' => $annex?->getNumber(),
        ]);
    }

    /**
     * The blanks the chosen trames ask for and this contract has not filled.
     *
     * Read from the wording, which is where the question is asked: a version
     * using `{{contract.custom.acompte}}` makes `acompte` mandatory, and no
     * list kept elsewhere can drift away from it.
     *
     * An empty string counts as missing. A trame asks for a value because the
     * sentence around it needs one, and sealing "un acompte de  % à la
     * signature" would produce a signed document with a hole in it.
     *
     * @return list<string>
     */
    protected function missingCustomFields(ContractInterface $contract): array
    {
        $filled = [];

        foreach ($contract->getCustomFields() as $key => $value) {
            if ('' !== $value) {
                $filled[$key] = true;
            }
        }

        $missing = [];

        foreach ($this->partsOf($contract) as $version) {
            foreach ($this->customFields->keysOf($version) as $key) {
                if (!isset($filled[$key])) {
                    $missing[$key] = true;
                }
            }
        }

        return array_keys($missing);
    }

    /**
     * The language that prevails over the whole document.
     *
     * Read from the parts rather than stored on the contract, because it is a
     * property of the wording: a trame written in three languages answered the
     * question when it was published, and a contract built from it inherits the
     * answer.
     *
     * Parts that name none are simply single-language wordings and have nothing
     * to say here. Parts that name two different ones are a contradiction
     * nobody can resolve at freeze time, and sealing it would produce a
     * document whose body and annex each claim authority. Refused, named.
     *
     * @throws FieldException when the parts disagree
     */
    protected function governingLocaleOf(ContractInterface $contract): ?string
    {
        $declared = [];

        foreach ($this->partsOf($contract) as $version) {
            $locale = $version->getGoverningLocale();

            if (null !== $locale) {
                $declared[$locale] = true;
            }
        }

        if (1 < count($declared)) {
            throw new FieldException('annexVersion', $this->translator->trans('backend.accounting.contracts.errors.governing_locale_conflict', ['{locales}' => implode(', ', array_keys($declared))]));
        }

        return array_key_first($declared);
    }

    /**
     * The clause, written in the language of the document that carries it.
     *
     * Two sentences rather than one when the reader is holding a translation:
     * somebody signing the Spanish version of a French contract is entitled to
     * be told, in Spanish, that the French text is the one a judge will read.
     */
    protected function governingLanguageClause(string $documentLocale, string $governingLocale): string
    {
        $language = $this->translator->trans('accounting.contract.language.'.$governingLocale, [], null, $documentLocale);

        $paragraphs = [
            $this->translator->trans('accounting.contract.governing_language.body', ['{language}' => $language], null, $documentLocale),
        ];

        if ($documentLocale !== $governingLocale) {
            $paragraphs[] = $this->translator->trans('accounting.contract.governing_language.translation_notice', [
                '{language}' => $this->translator->trans('accounting.contract.language.'.$documentLocale, [], null, $documentLocale),
            ], null, $documentLocale);
        }

        return $this->renderer->governingLanguageSection(
            $this->translator->trans('accounting.contract.governing_language.heading', [], null, $documentLocale),
            $paragraphs,
        );
    }

    /**
     * The versions that make up the document, body first.
     *
     * Order is part of the document: an annex printed before the body it
     * annexes is a different document, and the hash would say so.
     *
     * @return array<string, ContractTemplateVersionInterface>
     */
    protected function partsOf(ContractInterface $contract): array
    {
        $parts = [];
        $body = $contract->getBodyVersion();
        $annex = $contract->getAnnexVersion();

        if ($body instanceof ContractTemplateVersionInterface) {
            $parts[ContractTemplateKindEnum::Body->value] = $body;
        }

        if ($annex instanceof ContractTemplateVersionInterface) {
            $parts[ContractTemplateKindEnum::Annex->value] = $annex;
        }

        return $parts;
    }

    /**
     * One part, as both the structure it came from and the HTML it produced.
     *
     * @param array<string, string> $values
     *
     * @return array{snapshot: array<string, mixed>, html: string}
     */
    protected function renderPart(
        string $role,
        ContractTemplateVersionInterface $version,
        string $locale,
        array $values,
    ): array {
        $translation = $version->getTranslation($locale);

        if (!$translation instanceof ContractTemplateVersionTranslationInterface) {
            throw new FieldException('locale', $this->translator->trans('backend.accounting.contracts.errors.locale_missing', ['{locale}' => $locale, '{template}' => $version->getTemplate()->getName()]));
        }

        $content = $translation->getContent();
        $blocks = is_array($content['blocks'] ?? null) ? $content['blocks'] : [];

        try {
            $body = $this->renderer->render($blocks, $values);
        } catch (UnrenderableBlockException $unrenderableBlockException) {
            // Turned into a field error rather than left to bubble: this is a
            // template somebody has to go and fix, and a 500 does not say
            // which block of which trame.
            throw new FieldException('bodyVersion', sprintf('%s (%s)', $unrenderableBlockException->getMessage(), $version->getTemplate()->getName()));
        }

        $title = $this->renderer->substitute($translation->getTitle(), $values);

        return [
            'snapshot' => [
                'role' => $role,
                'templateId' => $version->getTemplate()->getId(),
                'templateName' => $version->getTemplate()->getName(),
                'versionId' => $version->getId(),
                'versionNumber' => $version->getNumber(),
                'title' => $title,
                'blocks' => $blocks,
            ],
            'html' => sprintf('<section><h1>%s</h1>%s</section>', $title, $body),
        ];
    }

    protected function assertPublished(ContractTemplateVersionInterface $version, string $field): void
    {
        if ($version->isPublished()) {
            return;
        }

        throw new FieldException($field, $this->translator->trans('backend.accounting.contracts.errors.version_not_published', ['{template}' => $version->getTemplate()->getName(), '{number}' => (string) $version->getNumber()]));
    }

    /**
     * `CM-2026-0001`, or whatever prefix the settings carry.
     *
     * Yearly, because that is how a contract is quoted and filed, and because
     * the paper process this replaces already numbered them that way.
     */
    protected function nextReference(): string
    {
        $prefix = $this->settingRepository->get(
            ApplicationParameterEnum::AccountingContractPrefix->value,
            SequencePrefixEnum::Contract->value,
        ) ?? SequencePrefixEnum::Contract->value;

        return $this->sequenceGenerator->nextYearly($prefix, (int) new DateTimeImmutable()->format('Y'));
    }

    protected function createContract(): ContractInterface
    {
        return new Contract();
    }

    /** @return array<string, mixed> */
    protected function auditPayload(ContractInterface $contract): array
    {
        return [
            'reference' => $contract->getReference(),
            'customer' => $contract->getCustomer()->getLegalName(),
            'status' => $contract->getStatus()->value,
            'locale' => $contract->getLocale(),
        ];
    }
}
