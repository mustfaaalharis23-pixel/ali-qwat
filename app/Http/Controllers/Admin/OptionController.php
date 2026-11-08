<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GeneralDescription;
use App\Models\InvoiceCategory;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\SpecificDescription;
use App\Models\Supplier;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The choices of the selects of the admin application, searched as the user types: { data: [{ value, label, description }] }.
 * ?search= narrows them, ?ids[]= reads the chosen ones back by their ids, and ?with_trashed=1 keeps the deleted ones
 * (an edit form whose record points at one). At most 50 come back. Only names are shown, so any signed-in user may read them,
 * like the selects of the panel's forms.
 */
class OptionController extends Controller
{
    public function __invoke(Request $request, string $type): JsonResponse
    {
        [$query, $search, $label, $description] = $this->definition($type) ?? abort(404);

        $term = trim((string) $request->query('search', ''));

        if ($request->boolean('with_trashed') && method_exists($query->getModel(), 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereKey((array) $request->query('ids'));
        } elseif ($term !== '') {
            $search($query, $term);
        }

        return response()->json([
            'data' => $query->limit(50)->get()->map(fn (Model $model): array => [
                'value' => $model->getKey(),
                'label' => (string) $label($model),
                'description' => $description($model),
            ])->values(),
        ]);
    }

    /**
     * For each kind of choice: its query, how it is searched, its label, and the line under it.
     *
     * @return array{0: Builder<Model>, 1: Closure(Builder<Model>, string): void, 2: Closure(Model): string, 3: Closure(Model): ?string}|null
     */
    private function definition(string $type): ?array
    {
        $like = fn (string ...$columns): Closure => function (Builder $query, string $term) use ($columns): void {
            $query->where(function (Builder $query) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $query->orWhereLike($column, "%{$term}%", caseSensitive: false);
                }
            });
        };

        return match ($type) {
            'customers' => [Customer::query()->orderBy('name'), $like('name', 'phone'), fn (Customer $customer): string => $customer->name, fn (Customer $customer): ?string => $customer->phone],
            'suppliers' => [Supplier::query()->orderBy('name_ar'), $like('name_ar', 'name_zh', 'phone'), fn (Supplier $supplier): string => (string) ($supplier->name_ar ?: $supplier->name_zh), fn (Supplier $supplier): ?string => $supplier->name_zh],
            'items' => [Item::query()->active()->orderBy('name'), $like('name'), fn (Item $item): string => $item->name, fn (Item $item): ?string => null],
            'all-items' => [Item::query()->orderBy('name'), $like('name'), fn (Item $item): string => $item->name, fn (Item $item): ?string => $item->is_active ? null : __('filament/resources/item.fields.is_active').': ✕'],
            'colors' => [Color::query()->orderBy('name'), $like('name'), fn (Color $color): string => $color->name, fn (Color $color): ?string => null],
            'lens-powers' => [LensPower::query()->orderBy('value'), fn (Builder $query, string $term) => $query->whereRaw('CAST(value AS TEXT) LIKE ?', ["%{$term}%"]), fn (LensPower $power): string => (string) $power->value, fn (LensPower $power): ?string => null],
            'containers' => [Container::query()->latest('id'), $like('international_number'), fn (Container $container): string => (string) $container->international_number, fn (Container $container): ?string => $container->loading_date?->toDateString()],
            'invoice-categories' => [InvoiceCategory::query()->orderBy('name'), $like('name'), fn (InvoiceCategory $category): string => $category->name, fn (InvoiceCategory $category): ?string => null],
            'general-descriptions' => [GeneralDescription::query()->orderBy('name'), $like('name'), fn (GeneralDescription $description): string => $description->name, fn (GeneralDescription $description): ?string => null],
            'specific-descriptions' => [SpecificDescription::query()->with('generalDescription')->orderBy('name'), $like('name'), fn (SpecificDescription $description): string => $description->name, fn (SpecificDescription $description): ?string => $description->generalDescription?->name],
            'users' => [User::query()->orderBy('name'), $like('name', 'email'), fn (User $user): string => $user->name, fn (User $user): ?string => $user->email],
            'cashiers' => [User::query()->where('is_cashier', true)->orderBy('name'), $like('name', 'email'), fn (User $user): string => $user->name, fn (User $user): ?string => $user->email],
            default => null,
        };
    }
}
