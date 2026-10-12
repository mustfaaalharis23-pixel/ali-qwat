<?php

namespace App\Models;

use App\Enums\InvoiceType;
use Database\Factories\GeneralDescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'type', 'is_purchases_description', 'is_capital_movement'])]
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
     * The capital movement descriptions: the money the partners put in and take out, such as an investment or a return of capital.
     * Money under them is neither a profit nor an expense; it only changes what the company holds.
     * This is the only definition of a capital movement description.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function capitalMovements(Builder $query): void
    {
        $query->where('is_capital_movement', true);
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
            'is_capital_movement' => 'boolean',
        ];
    }
}
