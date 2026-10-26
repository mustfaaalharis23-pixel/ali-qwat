<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The pieces of one color a stock batch holds: its group's packets × the pieces of the color in a packet.
 * It is never created or changed by hand; it is a copy of the group, so it is written with the batch.
 */
#[Fillable([
    'batch_id',
    'color_id',
    'pieces',
])]
class BatchColor extends BaseModel
{
    use SoftDeletes;

    /**
     * The batch these pieces are in.
     *
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * The color of these pieces.
     *
     * @return BelongsTo<Color, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pieces' => 'float',
        ];
    }
}
