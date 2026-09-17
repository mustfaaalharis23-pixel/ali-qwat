<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AppPermission;
use App\Filament\Forms\Components\PermissionTree;
use App\Models\User;
use App\Permissions\PermissionDiscovery;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/resources/user.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('filament/resources/user.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(__('filament/resources/user.fields.password'))
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Toggle::make('is_super')
                    ->label(__('filament/resources/user.fields.is_super'))
                    ->superOnly(),
                Toggle::make('is_cashier')
                    ->label(__('filament/resources/user.fields.is_cashier')),
                Section::make(__('permissions.section'))
                    ->key('permissionsSection')
                    ->headerActions([
                        Action::make('copyPermissions')
                            ->label(__('permissions.copy.label'))
                            ->icon(Heroicon::DocumentDuplicate)
                            ->schema([
                                Select::make('user_id')
                                    ->label(__('permissions.copy.user'))
                                    ->options(fn (?Model $record): array => User::query()
                                        ->whereKeyNot($record?->getKey())
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->required(),
                            ])
                            ->action(function (array $data, Set $set): void {
                                $set('permissions', DB::table('user_permissions')
                                    ->where('user_id', $data['user_id'])
                                    ->pluck('permission')
                                    ->all());

                                Notification::make()
                                    ->title(__('permissions.copy.copied'))
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->schema([
                        PermissionTree::make('permissions')
                            ->hiddenLabel()
                            ->groups(fn (): array => self::permissionGroups()),
                    ])
                    ->columnSpanFull()
                    ->hiddenOn('create')
                    ->superOnly(),
            ]);
    }

    /**
     * One group per model or page, with its permissions and their labels, then one Lenses group with a permission per lens,
     * and last the sales application group, one permission per screen of the application.
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    private static function permissionGroups(): array
    {
        $discovery = new PermissionDiscovery;

        $groups = collect($discovery->discoverInApp())
            ->mapWithKeys(fn (array $permissions, string $class): array => [
                Str::snake(class_basename($class)) => [
                    'label' => self::groupLabel($class),
                    'permissions' => collect($permissions)
                        ->mapWithKeys(fn (string $permission): array => [$permission => self::abilityLabel($permission)])
                        ->all(),
                ],
            ])
            ->all();

        $lenses = $discovery->discoverLensesInApp();

        if ($lenses !== []) {
            $groups['lenses'] = [
                'label' => __('filament/navigation.groups.lenses'),
                'permissions' => collect($lenses)
                    ->mapWithKeys(fn (string $permission, string $lens): array => [$permission => $lens::label()])
                    ->all(),
            ];
        }

        $groups['app'] = [
            'label' => __('permissions.app.group'),
            'permissions' => AppPermission::options(),
        ];

        return $groups;
    }

    /**
     * A page is labeled with its sidebar label; a model with its plural label.
     */
    private static function groupLabel(string $class): string
    {
        if (is_subclass_of($class, Page::class)) {
            return $class::getNavigationLabel();
        }

        $key = 'filament/resources/'.Str::snake(class_basename($class)).'.plural_label';

        return Lang::has($key) ? __($key) : Str::headline(Str::plural(class_basename($class)));
    }

    private static function abilityLabel(string $permission): string
    {
        $ability = Str::after($permission, '.');

        return Lang::has("permissions.abilities.{$ability}") ? __("permissions.abilities.{$ability}") : Str::headline($ability);
    }
}
