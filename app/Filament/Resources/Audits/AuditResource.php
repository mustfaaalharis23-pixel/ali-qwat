<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Filament\Resources\Audits\Pages\ViewAudit;
use App\Filament\Resources\Audits\Schemas\AuditInfolist;
use App\Filament\Resources\Audits\Tables\AuditsTable;
use App\Models\Audit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class AuditResource extends Resource
{
    /**
     * The events an audit can have.
     *
     * @var list<string>
     */
    public const EVENTS = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getModelLabel(): string
    {
        return __('filament/resources/audit.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/audit.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/audit.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.system_administration');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAudits::route('/'),
            'view' => ViewAudit::route('/{record}'),
        ];
    }

    /**
     * The label of an audit event in the current language, such as Created.
     */
    public static function eventLabel(?string $event): string
    {
        $key = "filament/resources/audit.events.{$event}";

        return Lang::has($key) ? __($key) : Str::headline((string) $event);
    }

    /**
     * The badge color of an audit event.
     */
    public static function eventColor(?string $event): string
    {
        return match ($event) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted', 'forceDeleted' => 'danger',
            'restored' => 'info',
            default => 'gray',
        };
    }

    /**
     * The label of the audited model in the current language, such as Item.
     */
    public static function subjectTypeLabel(?string $subjectType): string
    {
        $model = class_basename((string) $subjectType);
        $key = 'filament/resources/'.Str::snake($model).'.label';

        return Lang::has($key) ? __($key) : Str::headline($model);
    }

    /**
     * The label of a field of the audited model in the current language: the model's own field label first, then the common audit field labels.
     */
    public static function attributeLabel(?string $subjectType, string $attribute): string
    {
        $modelKey = 'filament/resources/'.Str::snake(class_basename((string) $subjectType)).".fields.{$attribute}";
        $commonKey = "filament/resources/audit.attributes.{$attribute}";

        return match (true) {
            Lang::has($modelKey) => __($modelKey),
            Lang::has($commonKey) => __($commonKey),
            default => Str::headline($attribute),
        };
    }
}
