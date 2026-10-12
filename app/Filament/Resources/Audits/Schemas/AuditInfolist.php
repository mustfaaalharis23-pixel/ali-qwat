<?php

namespace App\Filament\Resources\Audits\Schemas;

use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/audit.sections.details'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        TextEntry::make('event')
                            ->label(__('filament/resources/audit.fields.event'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => AuditResource::eventLabel($state))
                            ->color(fn (?string $state): string => AuditResource::eventColor($state)),
                        TextEntry::make('subject_type')
                            ->label(__('filament/resources/audit.fields.subject_type'))
                            ->formatStateUsing(fn (?string $state): string => AuditResource::subjectTypeLabel($state)),
                        TextEntry::make('subject_id')
                            ->label(__('filament/resources/audit.fields.subject_id')),
                        TextEntry::make('causer.name')
                            ->label(__('filament/resources/audit.fields.causer'))
                            ->placeholder(__('filament/resources/audit.system')),
                        TextEntry::make('created_at')
                            ->label(__('filament/resources/audit.fields.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->columnSpanFull(),
                Section::make(__('filament/resources/audit.sections.changes'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->schema([
                        RepeatableEntry::make('changes')
                            ->hiddenLabel()
                            ->state(fn (Audit $record): array => self::changes($record))
                            ->table([
                                TableColumn::make(__('filament/resources/audit.fields.attribute')),
                                TableColumn::make(__('filament/resources/audit.fields.old')),
                                TableColumn::make(__('filament/resources/audit.fields.new')),
                            ])
                            ->schema([
                                TextEntry::make('attribute'),
                                TextEntry::make('old')
                                    ->placeholder('—'),
                                TextEntry::make('new')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * One row per field of the record: its label, the value before, and the value after.
     *
     * @return list<array{attribute: string, old: ?string, new: ?string}>
     */
    private static function changes(Audit $record): array
    {
        $old = $record->attribute_changes?->get('old') ?? [];
        $new = $record->attribute_changes?->get('attributes') ?? [];

        return collect(array_keys($old + $new))
            ->map(fn (string $attribute): array => [
                'attribute' => AuditResource::attributeLabel($record->subject_type, $attribute),
                'old' => self::formatValue($old[$attribute] ?? null),
                'new' => self::formatValue($new[$attribute] ?? null),
            ])
            ->all();
    }

    /**
     * A saved value as text: booleans as a check or a cross, and arrays as JSON.
     */
    private static function formatValue(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '✓' : '✗',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
