<?php

declare(strict_types=1);

namespace Aurora\Module\Documentation;

use Aurora\Core\Module\Contract\ModuleInterface;
use Aurora\Core\Module\Contract\ModuleNavViewProviderInterface;
use Aurora\Core\Module\Nav\ModuleNavGroup;
use Aurora\Core\Module\Nav\ModuleNavView;
use Aurora\Core\Module\Nav\NavItem;
use Aurora\Core\Module\Nav\NavSection;

/**
 * The manual, as a section of the back office.
 *
 * No toggle and no permission of its own: whoever may open the back office
 * may read how it works. Gating help behind a privilege is how a product
 * ends up with a manual nobody finds.
 *
 * No context class either, because there is nothing to configure: the pages
 * ship with the package and a client cannot write them. That is the point of
 * moving them out of the publication system.
 */
final readonly class DocumentationModule implements ModuleInterface, ModuleNavViewProviderInterface
{
    /**
     * The panel the module view mounts under its link.
     *
     * The rubrics are the case a list of links cannot express: ninety pages
     * are not ninety `NavItem`s, they come from files rather than from routes,
     * and what the reader needs above them - a search field - is not something
     * a list of links carries.
     */
    private const string RUBRICS_PANEL = 'documentation/backend/documentation/RubricsPanel';

    public function getId(): string
    {
        return 'documentation';
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function getNavSections(): array
    {
        return [new NavSection('documentation', [$this->documentationNavItem()], priority: 900)];
    }

    public function getCatalogNavSections(): array
    {
        return $this->getNavSections();
    }

    /**
     * The menu the reader gets while they are reading the manual.
     *
     * The panel is the whole point, and the single entry above it is the row
     * that brought them here: the module has one destination, so there is
     * nothing else to list. What the panel replaces is a `w-64` column inside
     * the page, which cost the text a quarter of its width on every screen
     * and stacked above it on a telephone.
     *
     * Never null, unlike every other module's: the manual has no toggle and no
     * privilege, so there is no state in which it is present in the menu but
     * has no second level.
     */
    public function getModuleNavView(): ModuleNavView
    {
        return new ModuleNavView(
            'documentation',
            [new ModuleNavGroup('destinations', [$this->documentationNavItem()])],
            panelComponent: self::RUBRICS_PANEL,
        );
    }

    private function documentationNavItem(): NavItem
    {
        return new NavItem(
            'backend_documentation',
            'backend.nav.documentation',
            'book-open',
            activeColor: 'sky',
            activeRoutePrefix: 'backend_documentation',
            descriptionKey: 'backend.nav.documentation_description',
        );
    }
}
