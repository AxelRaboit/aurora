<?php

declare(strict_types=1);

namespace Aurora\Module\Studio;

use Aurora\Core\Module\Contract\ModuleInterface;
use Aurora\Core\Module\Contract\ModuleToggleProviderInterface;
use Aurora\Core\Module\Nav\NavItem;
use Aurora\Core\Module\Nav\NavPermission;
use Aurora\Core\Module\Nav\NavSection;
use Aurora\Module\Configuration\Setting\Enum\ModuleParameterEnum;

/**
 * Studio: what you sell to a client, and what you deliver to them.
 *
 * **The rule that decides what belongs here**, because a module named this
 * broadly has no other defence against becoming a drawer: Studio holds what is
 * sold and what is delivered. Not the tools it is made with. Customers,
 * contracts and presentations belong; notes, documents and the calendar do not,
 * and each of those has a module of its own.
 *
 * It was called Accounting until 0.9.x, which named one corner of it and
 * excluded the rest. The rename was paid for by a question a narrower name
 * could not answer: a deck of slides is addressed to a customer, and a module
 * may not hold a relation to another module's entity. Either the two live
 * together or the relation cannot exist.
 *
 * The module has no landing page of its own. Every destination it owns is a
 * real screen, so a row in the menu always leads somewhere that shows
 * something - the shape Notes already uses. A section with a single "Studio"
 * tile in front of the list it would immediately redirect to is one click that
 * tells the reader nothing.
 */
final readonly class StudioModule implements ModuleInterface, ModuleToggleProviderInterface
{
    public function __construct(private StudioContext $studioContext) {}

    public function getId(): string
    {
        return 'studio';
    }

    public function getPermissions(): array
    {
        return [
            new NavPermission('studio.customers.view'),
            new NavPermission('studio.customers.create'),
            new NavPermission('studio.customers.edit'),
            new NavPermission('studio.customers.delete'),
            new NavPermission('studio.contract_templates.view'),
            new NavPermission('studio.contract_templates.create'),
            new NavPermission('studio.contract_templates.edit'),
            new NavPermission('studio.contract_templates.delete'),
            new NavPermission('studio.contracts.view'),
            new NavPermission('studio.contracts.create'),
            new NavPermission('studio.contracts.edit'),
            new NavPermission('studio.contracts.delete'),
            new NavPermission('studio.contracts.send'),
            new NavPermission('studio.contracts.countersign'),
            new NavPermission('studio.decks.view'),
            new NavPermission('studio.decks.create'),
            new NavPermission('studio.decks.edit'),
            new NavPermission('studio.decks.delete'),
            new NavPermission('studio.deck_categories.manage'),
        ];
    }

    public function getNavSections(): array
    {
        if (!$this->studioContext->isBackendEnabled()) {
            return [];
        }

        $items = [];

        if ($this->studioContext->areCustomersEnabled()) {
            $items[] = $this->customersNavItem();
        }

        if ($this->studioContext->areContractsEnabled()) {
            // Contracts before the trames they are built from: the list read
            // every week comes before the documents edited twice a year.
            $items[] = $this->contractsNavItem();
            $items[] = $this->contractTemplatesNavItem();
        }

        if ($this->studioContext->areDecksEnabled()) {
            $items[] = $this->decksNavItem();
        }

        if ([] === $items) {
            return [];
        }

        return [new NavSection('studio', $items, priority: 45)];
    }

    public function getCatalogNavSections(): array
    {
        return [new NavSection('studio', [
            $this->customersNavItem(),
            $this->contractsNavItem(),
            $this->contractTemplatesNavItem(),
            $this->decksNavItem(),
        ], priority: 45)];
    }

    public function getToggles(): array
    {
        return [
            ModuleParameterEnum::StudioBackend->toToggle(),
            ModuleParameterEnum::StudioCustomers->toToggle(),
            ModuleParameterEnum::StudioContracts->toToggle(),
            ModuleParameterEnum::StudioDecks->toToggle(),
        ];
    }

    private function contractsNavItem(): NavItem
    {
        return new NavItem(
            'backend_studio_contracts',
            'backend.nav.studio_contracts',
            'file-signature',
            requiredPrivilege: 'studio.contracts.view',
            descriptionKey: 'backend.nav.studio_contracts_description',
        );
    }

    private function contractTemplatesNavItem(): NavItem
    {
        return new NavItem(
            'backend_studio_contract_templates',
            'backend.nav.studio_contract_templates',
            'scroll-text',
            requiredPrivilege: 'studio.contract_templates.view',
            descriptionKey: 'backend.nav.studio_contract_templates_description',
        );
    }

    private function decksNavItem(): NavItem
    {
        return new NavItem(
            'backend_studio_decks',
            'backend.nav.studio_decks',
            'presentation',
            requiredPrivilege: 'studio.decks.view',
            descriptionKey: 'backend.nav.studio_decks_description',
        );
    }

    private function customersNavItem(): NavItem
    {
        return new NavItem(
            'backend_studio_customers',
            'backend.nav.studio_customers',
            'building-2',
            requiredPrivilege: 'studio.customers.view',
            descriptionKey: 'backend.nav.studio_customers_description',
        );
    }
}
