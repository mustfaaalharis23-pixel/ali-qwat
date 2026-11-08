<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Http\JsonResponse;

/**
 * The sidebar of the admin application: the screens the signed-in user may open, in the groups and order of the panel,
 * with the same labels and permissions, so both always show the same screens to the same user.
 */
class NavigationController extends Controller
{
    /**
     * The groups in the order they show; the screens without a group come first.
     *
     * @var list<string>
     */
    private const GROUPS = ['sales', 'purchases', 'items_management', 'lenses', 'accounting', 'reports', 'system_administration'];

    public function __invoke(): JsonResponse
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $entries = collect([...$panel->getResources(), ...$panel->getPages()])
            ->unique()
            ->filter(fn (string $class): bool => $class::shouldRegisterNavigation() && $class::canAccess())
            ->map(fn (string $class): array => [
                'key' => $class === Dashboard::class ? 'home' : $class::getSlug(),
                'label' => $class::getNavigationLabel(),
                'group' => $class::getNavigationGroup(),
                'icon' => $this->iconName($class::getNavigationIcon()),
                'path' => $class === Dashboard::class ? '/' : '/'.$class::getSlug(),
                'sort' => $class::getNavigationSort() ?? 0,
            ]);

        $groupLabels = collect(self::GROUPS)->mapWithKeys(fn (string $group): array => [__("filament/navigation.groups.{$group}") => $group]);

        return response()->json([
            'groups' => $entries
                ->groupBy(fn (array $entry): string => (string) $entry['group'])
                ->map(fn ($items, string $label): array => [
                    'key' => $groupLabels[$label] ?? null,
                    'label' => $label === '' ? null : $label,
                    'items' => $items->sortBy([['sort', 'asc'], ['label', 'asc']])->values()->map(fn (array $item): array => collect($item)->except(['group', 'sort'])->all())->all(),
                ])
                ->sortBy(fn (array $group): int => $group['label'] === null ? -1 : (int) array_search($group['key'], self::GROUPS, true))
                ->values()
                ->all(),
        ]);
    }

    private function iconName(mixed $icon): ?string
    {
        return match (true) {
            $icon instanceof BackedEnum => (string) $icon->value,
            is_string($icon) => $icon,
            default => null,
        };
    }
}
