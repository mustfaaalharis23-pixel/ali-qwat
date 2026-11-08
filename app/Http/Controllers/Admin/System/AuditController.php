<?php

namespace App\Http\Controllers\Admin\System;

use App\Filament\Resources\Audits\AuditResource as FilamentAuditResource;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\System\AuditResource;
use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The audits, read only, newest first. ?filter[event]=, ?filter[subject_type]=, ?filter[causer]= (a user id),
 * ?created_from=&created_until= (dates). Any record page shows its own audits with ?subject_type=&subject_id=,
 * where the type is the model's class (App\Models\Invoice) or its short name (invoice, general_description).
 */
class AuditController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Audit::class);

        $query = Audit::query()
            ->with(['causer' => fn (MorphTo $causer) => $causer->constrain([User::class => fn (Builder $query) => $query->withTrashed()])])
            ->when($request->filled('subject_type'), fn (Builder $query) => $query->where('subject_type', $this->morphClassOf((string) $request->query('subject_type'))))
            ->when($request->filled('subject_id'), fn (Builder $query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('created_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', (string) $request->query('created_from')))
            ->when($request->filled('created_until'), fn (Builder $query) => $query->whereDate('created_at', '<=', (string) $request->query('created_until')));

        $audits = $this->listRecords(
            $query,
            $request,
            searchable: ['id'],
            sortable: ['id' => 'id', 'created_at' => 'created_at'],
            filters: [
                'event' => fn (Builder $query, string $value) => $query->where('event', $value),
                'subject_type' => fn (Builder $query, string $value) => $query->where('subject_type', $this->morphClassOf($value)),
                'causer' => fn (Builder $query, string $value) => $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', (int) $value),
            ],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        return AuditResource::collection($audits);
    }

    public function show(int $audit): AuditResource
    {
        $record = Audit::query()->with(['causer' => fn (MorphTo $causer) => $causer->constrain([User::class => fn (Builder $query) => $query->withTrashed()])])->findOrFail($audit);
        Gate::authorize('view', $record);

        $resource = new AuditResource($record);
        $resource->withChanges = true;

        return $resource;
    }

    /**
     * The choices of the list's filters: the events, the record types that have audits, and the users.
     */
    public function filters(): JsonResponse
    {
        Gate::authorize('viewAny', Audit::class);

        return response()->json([
            'events' => array_map(fn (string $event): array => ['value' => $event, 'label' => FilamentAuditResource::eventLabel($event)], FilamentAuditResource::EVENTS),
            'subject_types' => Audit::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->map(fn (string $type): array => ['value' => $type, 'label' => FilamentAuditResource::subjectTypeLabel($type)])
                ->sortBy('label')
                ->values(),
            'causers' => User::withTrashed()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])
                ->values(),
        ]);
    }

    /**
     * The saved subject type of a model given by its class or its short name.
     */
    private function morphClassOf(string $type): string
    {
        $class = class_exists($type) ? $type : 'App\\Models\\'.Str::studly($type);

        return is_subclass_of($class, Model::class) ? (new $class)->getMorphClass() : $type;
    }
}
