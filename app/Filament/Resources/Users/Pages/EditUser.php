<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Permissions\SyncUserPermissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * The chosen permissions to save after the user is saved; null when the permissions section was not shown.
     *
     * @var list<string>|null
     */
    protected ?array $permissionsToSync = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Fill the permission tree with the user's saved permissions.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions'] = DB::table('user_permissions')
            ->where('user_id', $this->getRecord()->getKey())
            ->pluck('permission')
            ->all();

        return $data;
    }

    /**
     * Take the chosen permissions out of the user's data, to save them separately.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('permissions', $data)) {
            $this->permissionsToSync = array_values(array_filter(Arr::flatten((array) $data['permissions']), 'is_string'));

            unset($data['permissions']);
        }

        return $data;
    }

    /**
     * is_super is not mass assignable on the model, so it is filled explicitly here.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data)->save();

        return $record;
    }

    protected function afterSave(): void
    {
        if ($this->permissionsToSync === null) {
            return;
        }

        (new SyncUserPermissions)->handle(auth()->user(), $this->getRecord(), $this->permissionsToSync);
    }
}
