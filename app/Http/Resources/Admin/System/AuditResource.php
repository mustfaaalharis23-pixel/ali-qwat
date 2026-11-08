<?php

namespace App\Http\Resources\Admin\System;

use App\Filament\Resources\Audits\AuditResource as FilamentAuditResource;
use App\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An audit of a change on a record: the event, the record, who made it and when. Its own page also lists each field
 * with its label in the current language, the value before, and the value after. Audits are read only.
 *
 * @mixin Audit
 */
class AuditResource extends JsonResource
{
    /**
     * Whether the changed fields are sent, which only the audit's own page asks for.
     */
    public bool $withChanges = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => [
                'value' => $this->event,
                'label' => FilamentAuditResource::eventLabel($this->event),
                'color' => FilamentAuditResource::eventColor($this->event),
            ],
            'subject_type' => $this->subject_type,
            'subject_type_label' => FilamentAuditResource::subjectTypeLabel($this->subject_type),
            'subject_id' => $this->subject_id,
            'causer' => $this->causer === null ? null : ['id' => $this->causer->getKey(), 'name' => $this->causer->name ?? null],
            'created_at' => $this->created_at?->toIso8601String(),
            'changes' => $this->when($this->withChanges, fn (): array => $this->changes()),
            'can' => ['view' => true, 'update' => false, 'delete' => false, 'restore' => false, 'force_delete' => false],
        ];
    }

    /**
     * One row per field of the record: its label, the value before, and the value after.
     *
     * @return list<array{attribute: string, old: ?string, new: ?string}>
     */
    private function changes(): array
    {
        $old = $this->attribute_changes?->get('old') ?? [];
        $new = $this->attribute_changes?->get('attributes') ?? [];

        return collect(array_keys($old + $new))
            ->map(fn (string $attribute): array => [
                'attribute' => FilamentAuditResource::attributeLabel($this->subject_type, $attribute),
                'old' => self::formatValue($old[$attribute] ?? null),
                'new' => self::formatValue($new[$attribute] ?? null),
            ])
            ->values()
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
