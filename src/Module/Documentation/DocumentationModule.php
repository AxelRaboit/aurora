<?php

declare(strict_types=1);

namespace Aurora\Module\Documentation;

use Aurora\Core\Module\Contract\ModuleInterface;
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
final readonly class DocumentationModule implements ModuleInterface
{
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
        return [
            new NavSection('documentation', [
                new NavItem(
                    'backend_documentation',
                    'backend.nav.documentation',
                    'book-open',
                    activeColor: 'sky',
                    activeRoutePrefix: 'backend_documentation',
                    descriptionKey: 'backend.nav.documentation_description',
                ),
            ], priority: 900),
        ];
    }

    public function getCatalogNavSections(): array
    {
        return $this->getNavSections();
    }
}
