<?php

namespace App\Http\Controllers\Admin\System;

use App\Enums\AppPermission;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\System\UserResource;
use App\Models\User;
use App\Permissions\PermissionDiscovery;
use App\Permissions\SyncUserPermissions;
use Filament\Pages\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The users. is_active, is_cashier, and is_super are not mass assignable, so they are filled explicitly.
 * Only a super user makes a user super, and reads or changes a user's permissions (SyncUserPermissions refuses anyone else).
 * A user is never deleted forever, not even by a super user (Gate::before).
 */
class UserController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = $this->listRecords(
            User::query(),
            $request,
            searchable: ['id', 'name', 'email'],
            sortable: ['id' => 'id', 'name' => 'name', 'email' => 'email'],
            defaultSort: ['column' => 'id', 'direction' => 'asc'],
        );

        return UserResource::collection($users)->additional([
            'can' => ['create' => Gate::allows('create', User::class)],
        ]);
    }

    public function show(int $user): UserResource
    {
        $record = User::withTrashed()->findOrFail($user);
        Gate::authorize('view', $record);

        return $this->resource($record);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $data = $this->validated($request);

        $user = new User;
        $user->forceFill($data)->save();

        return $this->resource($user)->response()->setStatusCode(201);
    }

    public function update(Request $request, int $user): UserResource
    {
        $record = User::withTrashed()->findOrFail($user);
        Gate::authorize('update', $record);

        $data = $this->validated($request, $record);

        DB::transaction(function () use ($request, $record, $data): void {
            $record->forceFill($data)->save();

            if ($request->user()->is_super === true && $request->has('permissions')) {
                $permissions = array_values(array_filter(Arr::flatten((array) $request->input('permissions')), 'is_string'));
                (new SyncUserPermissions)->handle($request->user(), $record, $permissions);
            }
        });

        return $this->resource($record->refresh());
    }

    public function destroy(int $user): JsonResponse
    {
        $record = User::query()->findOrFail($user);
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(status: 204);
    }

    public function restore(int $user): UserResource
    {
        $record = User::onlyTrashed()->findOrFail($user);
        Gate::authorize('restore', $record);

        $record->restore();

        return $this->resource($record);
    }

    public function forceDelete(int $user): JsonResponse
    {
        $record = User::withTrashed()->findOrFail($user);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * The saved permissions of a user, to copy them onto another user's tree. Super users only.
     */
    public function permissions(Request $request, int $user): JsonResponse
    {
        abort_unless($request->user()->is_super === true, 403);

        $record = User::withTrashed()->findOrFail($user);

        return response()->json([
            'data' => DB::table('user_permissions')->where('user_id', $record->id)->orderBy('permission')->pluck('permission')->all(),
        ]);
    }

    /**
     * The permission tree: one group per model or page with its permissions, then the lenses, then the sales application.
     * Super users only.
     */
    public function permissionGroups(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_super === true, 403);

        $discovery = new PermissionDiscovery;
        $groups = [];

        foreach ($discovery->discoverInApp() as $class => $permissions) {
            $groups[] = [
                'key' => Str::snake(class_basename($class)),
                'label' => $this->groupLabel($class),
                'permissions' => array_map(fn (string $permission): array => ['name' => $permission, 'label' => $this->abilityLabel($permission)], $permissions),
            ];
        }

        $lenses = $discovery->discoverLensesInApp();

        if ($lenses !== []) {
            $groups[] = [
                'key' => 'lenses',
                'label' => __('filament/navigation.groups.lenses'),
                'permissions' => collect($lenses)
                    ->map(fn (string $permission, string $lens): array => ['name' => $permission, 'label' => $lens::label()])
                    ->values()
                    ->all(),
            ];
        }

        $groups[] = [
            'key' => 'app',
            'label' => __('permissions.app.group'),
            'permissions' => collect(AppPermission::options())
                ->map(fn (string $label, string $permission): array => ['name' => $permission, 'label' => $label])
                ->values()
                ->all(),
        ];

        return response()->json(['data' => $groups]);
    }

    private function resource(User $user): UserResource
    {
        $resource = new UserResource($user);
        $resource->withPermissions = true;

        return $resource;
    }

    /**
     * A page is labeled with its sidebar label; a model with its plural label.
     */
    private function groupLabel(string $class): string
    {
        if (is_subclass_of($class, Page::class)) {
            return (string) $class::getNavigationLabel();
        }

        $key = 'filament/resources/'.Str::snake(class_basename($class)).'.plural_label';

        return Lang::has($key) ? __($key) : Str::headline(Str::plural(class_basename($class)));
    }

    private function abilityLabel(string $permission): string
    {
        $ability = Str::after($permission, '.');

        return Lang::has("permissions.abilities.{$ability}") ? __("permissions.abilities.{$ability}") : Str::headline($ability);
    }

    /**
     * The fields of the user. The password is kept when it is left empty on an edit. is_super is taken only from a super user.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user)],
            'password' => [$user === null ? 'required' : 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_cashier' => ['sometimes', 'boolean'],
            'is_super' => ['sometimes', 'boolean'],
        ], attributes: [
            'name' => __('filament/resources/user.fields.name'),
            'email' => __('filament/resources/user.fields.email'),
            'password' => __('filament/resources/user.fields.password'),
            'is_active' => __('filament/resources/user.fields.is_active'),
            'is_cashier' => __('filament/resources/user.fields.is_cashier'),
            'is_super' => __('filament/resources/user.fields.is_super'),
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->user()->is_super !== true) {
            unset($data['is_super']);
        }

        if ($user === null) {
            $data['is_active'] ??= true;
        }

        foreach (['is_active', 'is_cashier', 'is_super'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = (bool) $data[$flag];
            }
        }

        return $data;
    }
}
