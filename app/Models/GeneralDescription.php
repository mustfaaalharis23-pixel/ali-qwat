<?php

namespace App\Models;

use App\Enums\InvoiceType;
use Database\Factories\GeneralDescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'type', 'is_purchases_description'])]
class GeneralDescription extends BaseModel
{
    /** @use HasFactory<GeneralDescriptionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The single purchases general description, which the migration adds.
     */
    public static function purchases(): self
    {
        return static::query()->where('is_purchases_description', true)->firstOrFail();
    }

    /**
     * Whether a specific description under this general description has a transfer type, so it moves money out of a sales box.
     */
    public function holdsTransferDescriptions(): bool
    {
        return $this->specificDescriptions()->whereNotNull('transfer_type')->exists();
    }

    /**
     * The specific descriptions under this general description.
     *
     * @return HasMany<SpecificDescription, $this>
     */
    public function specificDescriptions(): HasMany
    {
        return $this->hasMany(SpecificDescription::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'is_purchases_description' => 'boolean',
        ];
    }
}
