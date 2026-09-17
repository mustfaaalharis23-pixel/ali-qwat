<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * The user as the app needs them: who they are, whether they are a cashier, and which screens of the
     * application they are allowed to see.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_cashier' => $this->is_cashier,
            'permissions' => $this->appPermissions(),
        ];
    }
}
