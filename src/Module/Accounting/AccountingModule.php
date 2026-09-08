<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting;

use Aurora\Core\Module\Contract\ModuleInterface;
use Aurora\Core\Module\Contract\ModuleToggleProviderInterface;
use Aurora\Core\Module\Nav\NavItem;
use Aurora\Core\Module\Nav\NavPermission;
use Aurora\Core\Module\Nav\NavSection;
use Aurora\Module\Configuration\Setting\Enum\ModuleParameterEnum;

/**
 * Accounting: who the work is sold to, and on what terms.
 *
 * The module has no landing page of its own. Every destination it owns is a
 * real screen, so a row in the menu always leads somewhere that shows
 * something - the shape Notes already uses. A section with a single
 * "Accounting" tile in front of the list it would immediately redirect to is
 * one click that tells the reader nothing.
 */
final readonly class AccountingModule implements ModuleInterface, ModuleToggleProviderInterface
{
    public function __construct(private AccountingContext $accountingContext) {}

    public function getId(): string
    {
        return 'accounting';
    }

    public function getPermissions(): array
    {
        return [
            new NavPermission('accounting.customers.view'),
            new NavPermission('accounting.customers.create'),
            new NavPermission('accounting.customers.edit'),
            new NavPermission('accounting.customers.delete'),
            new NavPermission('accounting.contract_templates.view'),
            new NavPermission('accounting.contract_templates.create'),
            new NavPermission('accounting.contract_templates.edit'),
            new NavPermission('accounting.contract_templates.delete'),
        ];
    }

    public function getNavSections(): array
    {
        if (!$this->accountingContext->isBackendEnabled()) {
            return [];
        }

        $items = [];

        if ($this->accountingContext->areCustomersEnabled()) {
            $items[] = $this->customersNavItem();
        }

        if ($this->accountingContext->areContractsEnabled()) {
            $items[] = $this->contractTemplatesNavItem();
        }

        if ([] === $items) {
            return [];
        }

        return [new NavSection('accounting', $items, priority: 45)];
    }

    public function getCatalogNavSections(): array
    {
        return [new NavSection('accounting', [
            $this->customersNavItem(),
            $this->contractTemplatesNavItem(),
        ], priority: 45)];
    }

    public function getToggles(): array
    {
        return [
            ModuleParameterEnum::AccountingBackend->toToggle(),
            ModuleParameterEnum::AccountingCustomers->toToggle(),
            ModuleParameterEnum::AccountingContracts->toToggle(),
        ];
    }

    private function contractTemplatesNavItem(): NavItem
    {
        return new NavItem(
            'backend_accounting_contract_templates',
            'backend.nav.accounting_contract_templates',
            'scroll-text',
            requiredPrivilege: 'accounting.contract_templates.view',
            descriptionKey: 'backend.nav.accounting_contract_templates_description',
        );
    }

    private function customersNavItem(): NavItem
    {
        return new NavItem(
            'backend_accounting_customers',
            'backend.nav.accounting_customers',
            'building-2',
            requiredPrivilege: 'accounting.customers.view',
            descriptionKey: 'backend.nav.accounting_customers_description',
        );
    }
}
