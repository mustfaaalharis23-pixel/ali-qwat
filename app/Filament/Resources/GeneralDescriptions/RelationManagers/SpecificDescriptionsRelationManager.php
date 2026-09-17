<?php

namespace App\Filament\Resources\GeneralDescriptions\RelationManagers;

use App\Enums\OrderPaymentType;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\GeneralDescription;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The specific descriptions under a general description, shown on its page.
 * They are managed from the details page too, except under the purchases description, whose descriptions are created with the items.
 */
class SpecificDescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'specificDescriptions';

    /**
     * The purchases description's list is read only; any other general description's list can be managed.
     */
    public function isReadOnly(): bool
    {
        /** @var GeneralDescription $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $ownerRecord->is_purchases_description;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/specific_description.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/resources/specific_description.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('transfer_type')
                    ->label(__('filament/resources/specific_description.fields.transfer_type'))
                    ->options(OrderPaymentType::class)
                    ->placeholder('—')
                    ->superOnly(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel(__('filament/resources/specific_description.label'))
            ->pluralModelLabel(__('filament/resources/specific_description.plural_label'))
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/specific_description.fields.name'))
                    ->searchable(),
                TextColumn::make('transfer_type')
                    ->label(__('filament/resources/specific_description.fields.transfer_type'))
                    ->badge()
                    ->placeholder('—'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
