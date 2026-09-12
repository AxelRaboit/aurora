<?php

declare(strict_types=1);

namespace Aurora\Module\General\Trash\View;

use Aurora\Core\Module\Service\ModuleAccessChecker;
use Aurora\Core\Trash\TrashSummary;
use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnum;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\General\Trash\Service\TrashOverviewService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The payload of the overview screen.
 *
 * Names the module toggles as plain settings keys, like the dashboard does, so
 * this shell never imports a business module's parameter enum and cannot
 * disagree with the side menu about what is switched on.
 */
final readonly class TrashViewBuilder
{
    /**
     * Module id → the settings key gating it.
     *
     * @var array<string, string>
     */
    private const array MODULE_TOGGLES = [
        'ged' => 'modules_ged_backend',
        'editorial' => 'modules_editorial_backend',
        'notes' => 'modules_notes_backend',
    ];

    public function __construct(
        private TrashOverviewService $trashOverviewService,
        private ModuleAccessChecker $moduleAccessChecker,
        private SettingRepository $settingRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexView(): array
    {
        $enabled = [];
        foreach (self::MODULE_TOGGLES as $moduleId => $toggle) {
            if ($this->moduleAccessChecker->isEnabled($toggle)) {
                $enabled[] = $moduleId;
            }
        }

        $summaries = $this->trashOverviewService->getSummaries($enabled);

        return [
            'trashes' => array_map($this->present(...), $summaries),
            'retentionDays' => (int) $this->settingRepository->get(
                ApplicationParameterEnum::TrashAutoPurgeDays->value,
                ApplicationParameterEnum::TrashAutoPurgeDays->getDefaultValue(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(TrashSummary $summary): array
    {
        return [
            'key' => $summary->key,
            'labelKey' => $summary->labelKey,
            'icon' => $summary->icon,
            'count' => $summary->count,
            'oldestDeletedAt' => $summary->oldestDeletedAt?->format(DATE_ATOM),
            'url' => null === $summary->route
                ? null
                : $this->urlGenerator->generate($summary->route, $summary->routeParameters),
        ];
    }
}
