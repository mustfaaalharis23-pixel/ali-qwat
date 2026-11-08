<?php

namespace App\Http\Resources\Admin\System;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * A user. Only a super user sees who is a super user, and reads the user's permissions (on the user's own page).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Whether the user's saved permissions are sent, which only the user's own page asks for.
     */
    public bool $withPermissions = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerIsSuper = $request->user()?->is_super === true;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'is_cashier' => (bool) $this->is_cashier,
            'is_super' => $this->when($viewerIsSuper, fn (): bool => (bool) $this->is_super),
            'permissions' => $this->when($this->withPermissions && $viewerIsSuper, fn (): array => DB::table('user_permissions')
                ->where('user_id', $this->id)
                ->orderBy('permission')
                ->pluck('permission')
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => ListsRecords::abilitiesOf($this->resource),
        ];
    }
}
