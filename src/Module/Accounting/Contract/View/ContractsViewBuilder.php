<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\View;

use Aurora\Core\Locale\Service\LocaleOptionsProviderInterface;
use Aurora\Core\Money\Enum\CurrencyEnum;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateInterface;
use Aurora\Module\Accounting\Contract\Enum\ContractTemplateKindEnum;
use Aurora\Module\Accounting\Contract\Repository\ContractRepository;
use Aurora\Module\Accounting\Contract\Repository\ContractTemplateRepository;
use Aurora\Module\Accounting\Contract\Serializer\ContractSerializerInterface;
use Aurora\Module\Accounting\Customer\Entity\CustomerInterface;
use Aurora\Module\Accounting\Customer\Repository\CustomerRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ContractsViewBuilder
{
    public function __construct(
        private ContractRepository $contractRepository,
        private ContractTemplateRepository $templateRepository,
        private CustomerRepository $customerRepository,
        private ContractSerializerInterface $serializer,
        private LocaleOptionsProviderInterface $localeOptions,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /** @return array<string, mixed> */
    public function indexView(): array
    {
        return [
            'contracts' => $this->contracts(),
            'customers' => $this->customerOptions(),
            // Only what can actually produce a document: live templates with
            // something published. Offering the rest means offering a dead end.
            'bodies' => $this->templateOptions(ContractTemplateKindEnum::Body),
            'annexes' => $this->templateOptions(ContractTemplateKindEnum::Annex),
            'locales' => $this->localeOptions->getActiveOptions(),
            'currencies' => $this->currencyOptions(),
            'createPath' => $this->urlGenerator->generate('backend_accounting_contracts_create'),
            'updatePath' => $this->urlGenerator->generate('backend_accounting_contracts_update', ['id' => '__id__']),
            'deletePath' => $this->urlGenerator->generate('backend_accounting_contracts_delete', ['id' => '__id__']),
            'freezePath' => $this->urlGenerator->generate('backend_accounting_contracts_freeze', ['id' => '__id__']),
            'showPath' => $this->urlGenerator->generate('backend_accounting_contracts_show', ['id' => '__id__']),
        ];
    }

    /** @return array<string, mixed> */
    public function showView(ContractInterface $contract): array
    {
        return [
            'contract' => $this->serializer->serializeDocument($contract),
            'indexPath' => $this->urlGenerator->generate('backend_accounting_contracts'),
            'freezePath' => $this->urlGenerator->generate('backend_accounting_contracts_freeze', ['id' => $contract->getId()]),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function contracts(): array
    {
        return array_map($this->serializer->serialize(...), $this->contractRepository->findAllForIndex());
    }

    /** @return array<string, mixed> */
    public function listPayload(): array
    {
        return ['success' => true, 'contracts' => $this->contracts()];
    }

    /** @return list<array{value: string, label: string}> */
    private function customerOptions(): array
    {
        return array_map(
            static fn (CustomerInterface $customer): array => [
                'value' => (string) $customer->getId(),
                'label' => $customer->getLegalName(),
            ],
            $this->customerRepository->findAllOrdered(),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function templateOptions(ContractTemplateKindEnum $kind): array
    {
        return array_map(
            static fn (ContractTemplateInterface $template): array => [
                'value' => (string) $template->getId(),
                'label' => $template->getName(),
            ],
            $this->templateRepository->findSelectable($kind),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function currencyOptions(): array
    {
        return array_map(
            static fn (CurrencyEnum $currency): array => [
                'value' => $currency->value,
                'label' => sprintf('%s (%s)', $currency->value, $currency->symbol()),
            ],
            CurrencyEnum::cases(),
        );
    }
}
