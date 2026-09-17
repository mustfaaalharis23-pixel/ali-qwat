<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AppPermission;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
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
class User extends Authenticatable implements FilamentUser
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
     * Only active users that are not deleted can access the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    /**
     * Whether the user has the given permission, such as sales_invoice.view.
     */
    public function hasPermission(string $permission): bool
    {
        return DB::table('user_permissions')
            ->where('user_id', $this->id)
            ->where('permission', $permission)
            ->exists();
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
