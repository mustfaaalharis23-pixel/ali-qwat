<?php

namespace App\Models;

use Database\Factories\ColorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * A color the pieces in a purchased packet come in, such as red. A packet holds a few colors, with a number of pieces of each,
 * and a sale is taken from one color. The list is kept by the users, so a new color is added any time.
 */
#[Fillable(['name'])]
class Color extends BaseModel
{
    /** @use HasFactory<ColorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The swatches of the usual color names, in Arabic and English, for the dots the screens draw beside a color.
     * A color is kept by its name only, so any other name gets a neutral dot.
     *
     * @var array<string, string>
     */
    private const SWATCHES = [
        'أحمر' => '#dc2626', 'أزرق' => '#2563eb', 'أسود' => '#111827', 'أبيض' => '#f9fafb', 'بني' => '#92400e',
        'أخضر' => '#16a34a', 'أصفر' => '#eab308', 'رصاصي' => '#6b7280', 'رمادي' => '#6b7280', 'وردي' => '#ec4899',
        'بنفسجي' => '#7c3aed', 'برتقالي' => '#ea580c', 'ذهبي' => '#d4a017', 'فضي' => '#c0c0c0', 'سمائي' => '#38bdf8',
        'نيلي' => '#1e3a8a', 'شفاف' => 'rgba(148, 163, 184, 0.25)',
        'red' => '#dc2626', 'blue' => '#2563eb', 'black' => '#111827', 'white' => '#f9fafb', 'brown' => '#92400e',
        'green' => '#16a34a', 'yellow' => '#eab308', 'gray' => '#6b7280', 'grey' => '#6b7280', 'pink' => '#ec4899',
        'purple' => '#7c3aed', 'orange' => '#ea580c', 'gold' => '#d4a017', 'silver' => '#c0c0c0', 'clear' => 'rgba(148, 163, 184, 0.25)',
    ];

    /**
     * The CSS color of the dot drawn beside the color named [name].
     */
    public static function swatch(?string $name): string
    {
        return self::SWATCHES[mb_strtolower(trim((string) $name))] ?? 'rgba(148, 163, 184, 0.55)';
    }

    /**
     * A color that is in the packets of a purchase line cannot be deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Color $color): void {
            $color->ensureIsOnNoLine();
        });
    }

    /**
     * The pieces of this color in the packets of purchase lines.
     *
     * @return HasMany<InvoiceItemColor, $this>
     */
    public function invoiceItemColors(): HasMany
    {
        return $this->hasMany(InvoiceItemColor::class);
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsOnNoLine(): void
    {
        if ($this->invoiceItemColors()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'invoice_items' => __('invoices.validation.color_on_lines'),
            ]);
        }
    }
}
