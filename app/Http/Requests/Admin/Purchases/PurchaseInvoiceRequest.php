<?php

namespace App\Http\Requests\Admin\Purchases;

use App\Enums\ExchangeRateSource;
use App\Invoices\FetchExchangeRate;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SpecificDescription;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

/**
 * What the purchase invoice form sends, checked by the same rules as the panel's form: a line is typed in packets or in dozens
 * (dozens must make whole packets), the packets of its groups add up to its packets, the pieces of its colors fill one packet,
 * the dollar rate is written as the market writes it, and the initial payment is never more than the invoice.
 * The yuan rate is read only when the user may change it; the price of one CBM only until the invoice entered a container.
 * Everything else is the models' own rules, applied when the invoice is saved.
 */
class PurchaseInvoiceRequest extends FormRequest
{
    /**
     * The dollar rate in dinars a new invoice starts with, and the lowest and highest that make sense,
     * in thousands of dinars for 100 dollars.
     */
    public const int DEFAULT_DINAR_RATE = 170;

    public const int MIN_DINAR_RATE = 100;

    public const int MAX_DINAR_RATE = 300;

    public const string ENTRY_IN_DOZENS = 'dozens';

    /**
     * Where the yuan rate the banks gave when the create page opened is kept, so the invoice is saved at it unless the user may change it.
     * It is kept on the server, never taken from the browser.
     */
    public const string BANK_RATE_SESSION_KEY = 'admin.purchase_invoice_bank_rate';

    /**
     * A bank rate older than this is fetched again.
     */
    private const int BANK_RATE_LIFETIME_MINUTES = 720;

    /**
     * @var array<int, Item|null>
     */
    private array $itemsByDescription = [];

    /**
     * A new invoice needs the create permission; an invoice is edited while its policy allows it.
     */
    public function authorize(): bool
    {
        return $this->isCreating() ? Gate::allows('create', Invoice::class) : Gate::allows('update', $this->invoice());
    }

    /**
     * The invoice being edited, or null on the create page.
     */
    public function invoice(): ?Invoice
    {
        $id = $this->route('invoice');

        return $id === null ? null : once(fn (): Invoice => Invoice::withTrashed()->purchases()->findOrFail($id));
    }

    public function isCreating(): bool
    {
        return $this->route('invoice') === null;
    }

    /**
     * The yuan rate the banks gave when the create page opened, and its bank; the rate is null when no bank answered.
     * When the page was never opened (or long ago), the banks are asked now, so the rate cannot be skipped.
     *
     * @return array{rate: ?float, source: ?string}
     */
    public static function bankRate(): array
    {
        $kept = session(self::BANK_RATE_SESSION_KEY);

        if (is_array($kept) && isset($kept['at']) && Carbon::parse($kept['at'])->diffInMinutes(now(), true) < self::BANK_RATE_LIFETIME_MINUTES) {
            return ['rate' => $kept['rate'], 'source' => $kept['source']];
        }

        return self::fetchBankRate();
    }

    /**
     * Asks the banks for the yuan rate and keeps what they answered (or that none answered) in the session.
     *
     * @return array{rate: ?float, source: ?string}
     */
    public static function fetchBankRate(): array
    {
        $fetched = (new FetchExchangeRate)->handle();
        $rate = ['rate' => $fetched['rate'] ?? null, 'source' => isset($fetched['source']) ? $fetched['source']->value : null];

        session([self::BANK_RATE_SESSION_KEY => [...$rate, 'at' => now()->toIso8601String()]]);

        return $rate;
    }

    /**
     * Whether the yuan rate is read from the form: for a user with the permission to edit it, or by anyone on the create page when no bank gave a rate.
     */
    public function canChangeExchangeRate(): bool
    {
        return Gate::allows('editExchangeRate', Invoice::class)
            || ($this->isCreating() && self::bankRate()['rate'] === null);
    }

    /**
     * The yuan rate and its source a new invoice is saved with: the bank's, unless a user with the permission typed another
     * one. A typed rate, or any rate when no bank answered, is manual.
     *
     * @return array{exchange_rate: mixed, exchange_rate_source: string}
     */
    public function exchangeRateForCreation(): array
    {
        $bank = self::bankRate();
        $typed = $this->validated('exchange_rate');
        $mayEdit = Gate::allows('editExchangeRate', Invoice::class);
        $keepsTheBankRate = $bank['rate'] !== null && (! $mayEdit || (float) $typed === (float) $bank['rate']);

        return [
            'exchange_rate' => $keepsTheBankRate ? $bank['rate'] : $typed,
            'exchange_rate_source' => $keepsTheBankRate ? (string) $bank['source'] : ExchangeRateSource::Manual->value,
        ];
    }

    public function isCbmPriceLocked(): bool
    {
        return $this->invoice()?->cbm_price_locked_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'invoice_category_id' => ['required', 'integer', Rule::exists('invoice_categories', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date'],
            'dinar_rate' => ['required', 'numeric', 'between:'.self::MIN_DINAR_RATE.','.self::MAX_DINAR_RATE],
            'notes' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', $this->storedFile('invoices')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.specific_description_id' => ['required', 'integer', Rule::exists('specific_descriptions', 'id')->whereNull('deleted_at')->whereNotNull('item_id')],
            'items.*.entry_unit' => ['nullable', Rule::in(['packets', self::ENTRY_IN_DOZENS])],
            'items.*.pieces_per_packet' => ['required', 'integer', 'min:0'],
            'items.*.boxes_per_carton' => ['required', 'numeric', 'gt:0'],
            'items.*.carton_cbm' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.colors' => ['nullable', 'array'],
            'items.*.colors.*.id' => ['nullable', 'integer'],
            'items.*.colors.*.color_id' => ['required', 'integer', 'distinct', Rule::exists('colors', 'id')->whereNull('deleted_at')],
            'items.*.colors.*.pieces' => ['required', 'integer', 'gt:0'],
            'items.*.groups' => ['nullable', 'array'],
            'items.*.groups.*.id' => ['nullable', 'integer'],
            'items.*.groups.*.lens_power_id' => ['nullable', 'integer', Rule::exists('lens_powers', 'id')->whereNull('deleted_at')],
            'items.*.groups.*.boxes_count' => ['required', 'integer', 'gt:0'],
        ];

        foreach ((array) $this->input('items', []) as $index => $line) {
            if (($line['entry_unit'] ?? null) === self::ENTRY_IN_DOZENS) {
                $rules["items.{$index}.sets"] = ['required', 'numeric', 'gt:0', $this->setsMakeWholePackets($index)];
                $rules["items.{$index}.boxes_count"] = ['nullable'];
            } else {
                $rules["items.{$index}.boxes_count"] = ['required', 'numeric', 'integer', 'gt:0'];
            }
        }

        if ($this->canChangeExchangeRate()) {
            $rules['exchange_rate'] = ['required', 'numeric', 'gt:0'];
        }

        if (! $this->isCbmPriceLocked()) {
            $rules['cbm_price'] = ['required', 'numeric', 'min:0'];
        }

        if ($this->isCreating()) {
            $rules['initial_payment'] = ['nullable', 'numeric', 'min:0', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (float) $value > $this->linesTotal() + 0.0001) {
                    $fail(__('filament/resources/purchase_invoice.validation.initial_payment_above_amount'));
                }
            }];
        }

        return $rules;
    }

    /**
     * The groups of a line add up to its packets, and the colors of a line fill its packet, as the panel's form checks them.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $groups = is_array($line['groups'] ?? null) ? $line['groups'] : [];
                $packets = (float) $this->packetsOf($line);

                if ($groups !== []) {
                    $spread = collect($groups)->sum(fn (mixed $group): float => (float) (is_array($group) ? ($group['boxes_count'] ?? 0) : 0));

                    if (abs($spread - $packets) > 0.0001) {
                        $validator->errors()->add("items.{$index}.groups", __('invoices.validation.groups_must_add_up', [
                            'spread' => Number::format($spread, maxPrecision: 2),
                            'packets' => Number::format($packets, maxPrecision: 2),
                        ]));
                    }
                }

                $colors = is_array($line['colors'] ?? null) ? $line['colors'] : [];

                if ($colors !== []) {
                    $pieces = collect($colors)->sum(fn (mixed $color): float => (float) (is_array($color) ? ($color['pieces'] ?? 0) : 0));
                    $packet = (float) $this->piecesPerPacketOf($line);

                    if (abs($pieces - $packet) > 0.0001) {
                        $validator->errors()->add("items.{$index}.colors", __('invoices.validation.colors_must_fill_the_packet', [
                            'pieces' => Number::format($pieces, maxPrecision: 2),
                            'packet' => Number::format($packet, maxPrecision: 2),
                        ]));
                    }
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dinar_rate.between' => __('filament/resources/purchase_invoice.dinar_rate.invalid', ['min' => self::MIN_DINAR_RATE, 'max' => self::MAX_DINAR_RATE]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $line = fn (string $field): string => __("filament/resources/invoice_item.fields.{$field}");

        return [
            'supplier_id' => __('filament/resources/invoice.fields.supplier_id'),
            'invoice_category_id' => __('filament/resources/invoice.fields.invoice_category_id'),
            'date' => __('filament/resources/invoice.fields.date'),
            'exchange_rate' => __('filament/resources/invoice.fields.exchange_rate'),
            'dinar_rate' => __('filament/resources/invoice.fields.dinar_rate'),
            'cbm_price' => __('filament/resources/invoice.fields.cbm_price'),
            'notes' => __('filament/resources/invoice.fields.notes'),
            'images.*' => __('filament/resources/invoice.fields.images'),
            'items' => __('filament/resources/purchase_invoice.fields.items'),
            'initial_payment' => __('filament/resources/purchase_invoice.fields.initial_payment'),
            'items.*.specific_description_id' => __('filament/resources/purchase_invoice.fields.item'),
            'items.*.boxes_count' => $line('boxes_count'),
            'items.*.sets' => $line('sets'),
            'items.*.pieces_per_packet' => $line('pieces_per_packet'),
            'items.*.boxes_per_carton' => $line('boxes_per_carton'),
            'items.*.carton_cbm' => $line('carton_cbm'),
            'items.*.unit_cost' => __('filament/resources/purchase_invoice.fields.unit_cost'),
            'items.*.colors.*.color_id' => $line('color_id'),
            'items.*.colors.*.pieces' => $line('pieces'),
            'items.*.groups.*.lens_power_id' => $line('lens_power_id'),
            'items.*.groups.*.boxes_count' => $line('boxes_count'),
        ];
    }

    /**
     * The line's packets: typed, or, for a line typed in dozens, the dozens × 12 ÷ the packet.
     *
     * @param  array<string, mixed>  $line
     */
    public function packetsOf(array $line): float|int|null
    {
        if (($line['entry_unit'] ?? null) !== self::ENTRY_IN_DOZENS) {
            return isset($line['boxes_count']) && is_numeric($line['boxes_count']) ? (float) $line['boxes_count'] : null;
        }

        $sets = (float) ($line['sets'] ?? 0);
        $packet = (float) $this->piecesPerPacketOf($line);

        if ($sets <= 0 || $packet <= 0) {
            return null;
        }

        $packets = $sets * Item::PIECES_IN_A_DOZEN / $packet;

        return abs($packets - round($packets)) <= 0.0001 ? (int) round($packets) : round($packets, 2);
    }

    /**
     * The pieces in one packet of the line: its item's when the item has a packet size, which never changes, or as typed.
     *
     * @param  array<string, mixed>  $line
     */
    public function piecesPerPacketOf(array $line): float
    {
        $itemPacket = $this->itemOf($line['specific_description_id'] ?? null)?->pieces_per_packet;

        return (float) ($itemPacket ?? ($line['pieces_per_packet'] ?? 0));
    }

    /**
     * The line's total price: its pieces × the unit cost of one piece, rounded to two decimals.
     *
     * @param  array<string, mixed>  $line
     */
    public function lineTotal(array $line): float
    {
        return round((float) $this->packetsOf($line) * $this->piecesPerPacketOf($line) * (float) ($line['unit_cost'] ?? 0), 2);
    }

    /**
     * The invoice's amount as the model will compute it: the sum of its lines' total prices.
     */
    private function linesTotal(): float
    {
        return (float) collect((array) $this->input('items', []))
            ->sum(fn (mixed $line): float => is_array($line) ? $this->lineTotal($line) : 0.0);
    }

    private function itemOf(mixed $descriptionId): ?Item
    {
        if (! is_numeric($descriptionId)) {
            return null;
        }

        $id = (int) $descriptionId;

        if (! array_key_exists($id, $this->itemsByDescription)) {
            $this->itemsByDescription[$id] = SpecificDescription::query()->with('item')->find($id)?->item;
        }

        return $this->itemsByDescription[$id];
    }

    /**
     * The typed dozens make whole packets of the line's packet: 21 dozens of a packet of 20 pieces are 12.6 packets, which is refused.
     */
    private function setsMakeWholePackets(int|string $index): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($index): void {
            $line = (array) $this->input("items.{$index}", []);
            $packets = $this->packetsOf($line);

            if (is_float($packets)) {
                $fail(__('invoices.validation.sets_not_whole_packets', [
                    'sets' => Number::format((float) $value, maxPrecision: 2),
                    'packet' => Number::format($this->piecesPerPacketOf($line), maxPrecision: 2),
                    'packets' => Number::format($packets, maxPrecision: 2),
                ]));
            }
        };
    }

    /**
     * A file uploaded before to the given folder of the public disk, so a path outside it is never saved.
     */
    private function storedFile(string $directory): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($directory): void {
            if (! is_string($value) || ! preg_match('#^'.preg_quote($directory, '#').'/[A-Za-z0-9._-]+$#', $value) || ! Storage::disk('public')->exists($value)) {
                $fail(__('validation.exists', ['attribute' => __('filament/resources/invoice.fields.images')]));
            }
        };
    }
}
