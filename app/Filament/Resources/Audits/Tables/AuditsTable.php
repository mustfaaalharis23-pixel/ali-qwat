<?php

namespace App\Filament\Resources\Audits\Tables;

use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Audit;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditsTable
{
    /**
     * The audits table. Inside a record's own audits tab, the record type and number are hidden because they are always the same.
     */
    public static function configure(Table $table, bool $showSubject = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('event')
                    ->label(__('filament/resources/audit.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => AuditResource::eventLabel($state))
                    ->color(fn (?string $state): string => AuditResource::eventColor($state)),
                TextColumn::make('subject_type')
                    ->label(__('filament/resources/audit.fields.subject_type'))
                    ->formatStateUsing(fn (?string $state): string => AuditResource::subjectTypeLabel($state))
                    ->visible($showSubject),
                TextColumn::make('subject_id')
                    ->label(__('filament/resources/audit.fields.subject_id'))
                    ->visible($showSubject),
                TextColumn::make('causer.name')
                    ->label(__('filament/resources/audit.fields.causer'))
                    ->placeholder(__('filament/resources/audit.system')),
                TextColumn::make('created_at')
                    ->label(__('filament/resources/audit.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('filament/resources/audit.fields.event'))
                    ->options(fn (): array => collect(AuditResource::EVENTS)
                        ->mapWithKeys(fn (string $event): array => [$event => AuditResource::eventLabel($event)])
                        ->all()),
                SelectFilter::make('subject_type')
                    ->label(__('filament/resources/audit.fields.subject_type'))
                    ->options(fn (): array => Audit::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type')
                        ->mapWithKeys(fn (string $type): array => [$type => AuditResource::subjectTypeLabel($type)])
                        ->all())
                    ->visible($showSubject),
                SelectFilter::make('causer')
                    ->label(__('filament/resources/audit.fields.causer'))
                    ->options(fn (): array => User::withTrashed()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $userId): Builder => $query
                            ->where('causer_type', (new User)->getMorphClass())
                            ->where('causer_id', $userId),
                    )),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label(__('filament/resources/audit.fields.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('filament/resources/audit.fields.created_until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
