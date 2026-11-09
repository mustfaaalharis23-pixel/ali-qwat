<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AppPermission;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * What this user is allowed inside the sales application, read from the database once.
     *
     * @var list<string>|null
     */
    protected ?array $appPermissions = null;

    /**
     * Whether the user has the given permission, such as sales_invoice.view.
     */
    public function hasPermission(string $permission): bool
    {
        $request = app()->bound('request') ? request() : null;

        // While a request is being handled its permissions are read once, so a list that checks every row's abilities
        // does not ask the database again for each one. Outside a request (a command, a test calling a policy) they are
        // always read fresh.
        if ($request?->route() === null) {
            return DB::table('user_permissions')
                ->where('user_id', $this->id)
                ->where('permission', $permission)
                ->exists();
        }

        $key = "user_permissions.{$this->id}";

        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, DB::table('user_permissions')->where('user_id', $this->id)->pluck('permission')->all());
        }

        return in_array($permission, $request->attributes->get($key), true);
    }

    /**
     * What this user is allowed inside the sales application. A super user is allowed everything.
     * It is read from the database once, so a list of records asks for it only one time.
     *
     * @return list<string>
     */
    public function appPermissions(): array
    {
        return $this->appPermissions ??= $this->is_super
            ? AppPermission::names()
            : DB::table('user_permissions')
                ->where('user_id', $this->id)
                ->whereIn('permission', AppPermission::names())
                ->pluck('permission')
                ->all();
    }

    /**
     * Whether this user is allowed the given thing inside the sales application.
     */
    public function allowedInApp(AppPermission $permission): bool
    {
        return in_array($permission->value, $this->appPermissions(), true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super' => 'boolean',
            'is_active' => 'boolean',
            'is_cashier' => 'boolean',
        ];
    }
}
