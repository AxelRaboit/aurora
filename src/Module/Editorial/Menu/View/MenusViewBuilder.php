<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Menu\View;

use Aurora\Core\Locale\Service\LocaleContextInterface;
use Aurora\Module\Editorial\Menu\Enum\MenuItemTargetTypeEnum;
use Aurora\Module\Editorial\Menu\Enum\MenuItemVisibilityEnum;
use Aurora\Module\Editorial\Menu\Repository\MenuRepository;
use Aurora\Module\Editorial\Menu\Serializer\MenuSerializerInterface;
use Aurora\Module\Editorial\PostType\Repository\PostTypeRepository;

/**
 * Builds the Twig payload consumed by the admin menus screen.
 */
final readonly class MenusViewBuilder
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuSerializerInterface $menuSerializer,
        private LocaleContextInterface $localeContext,
        private PostTypeRepository $postTypeRepository,
    ) {}

    /** @return array<string, mixed> */
    /** The menu a bare `/menus` should send the reader to, or null when there is none. */
    public function firstId(): ?int
    {
        $menus = $this->menuRepository->findAllWithItems();

        return [] === $menus ? null : $menus[0]->getId();
    }

    /** @param ?int $activeId the menu the address names */
    public function indexView(?int $activeId = null): array
    {
        return [
            'activeId' => $activeId,
            'menus' => array_map(
                $this->menuSerializer->serialize(...),
                $this->menuRepository->findAllWithItems(),
            ),
            // The form edits every active locale at once, so it needs the list
            // rather than inferring it from the translations that exist.
            'locales' => $this->localeContext->getActiveLocales(),
            'targetTypes' => $this->targetTypes(),
            'visibilities' => $this->visibilities(),
            // For the "heads this section" field. Every type, not only the
            // ones with an archive: a hub page introducing a type without one
            // is the case the field exists for.
            'postTypes' => $this->postTypes(),
        ];
    }

    /** @return list<array{value: string, labelKey: string, requiresTarget: bool, requiresUrl: bool}> */
    private function targetTypes(): array
    {
        return array_map(
            static fn (MenuItemTargetTypeEnum $case): array => [
                'value' => $case->value,
                'labelKey' => $case->labelKey(),
                'requiresTarget' => $case->requiresTargetId(),
                'requiresUrl' => $case->requiresCustomUrl(),
            ],
            MenuItemTargetTypeEnum::cases(),
        );
    }

    /** @return list<array{value: int, label: string}> */
    private function postTypes(): array
    {
        $types = [];

        foreach ($this->postTypeRepository->findAll() as $postType) {
            $id = $postType->getId();

            if (null === $id) {
                continue;
            }

            $types[] = ['value' => $id, 'label' => $postType->getLabel()];
        }

        return $types;
    }

    /** @return list<array{value: string, labelKey: string}> */
    private function visibilities(): array
    {
        return array_map(
            static fn (MenuItemVisibilityEnum $case): array => [
                'value' => $case->value,
                'labelKey' => $case->labelKey(),
            ],
            MenuItemVisibilityEnum::cases(),
        );
    }
}
