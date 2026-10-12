<?php

namespace App\Models;

use App\Enums\ExchangeRateSource;
use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceStage;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Metrics\Period;
use App\Observers\StockObserver;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[ObservedBy(StockObserver::class)]
#[Fillable([
    'name',
    'type',
    'general_description_id',
    'supplier_id',
    'invoice_category_id',
    'user_id',
    'cashier_id',
    'date',
    'exchange_rate',
    'exchange_rate_source',
    'dinar_rate',
    'cbm_price',
    'status',
    'original_invoice_id',
    'images',
    'notes',
])]
class Invoice extends BaseModel
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A new invoice is pending until it is confirmed, in the database and in memory.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => InvoiceStatus::Pending->value,
    ];

    /**
     * Whether the save in progress changes the invoice's price of one CBM or its dinar rate, which the lines' costs come from.
     */
    protected bool $costRatesChanged = false;

    /**
     * Before every save, wherever it is saved from, a purchase invoice keeps its general description,
     * the invoice must have the type of its general description, only a purchase invoice has a supplier or a container,
     * and its paid amount and remaining are set from its amount and payoffs.
     * A purchase invoice needs its price of one CBM, which can be changed only until the invoice first enters a container.
     * A new purchase invoice is named by the model, not typed: see namePurchaseInvoiceAfterSupplier(), and starts in production.
     * An invoice that has payoffs, or that belongs to a customer payment, cannot be deleted; its initial payment does not count,
     * because it goes with the invoice: deleting the invoice deletes it, and restoring the invoice brings it back.
     */
    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice): void {
            $invoice->ensurePurchaseInvoiceKeepsItsGeneralDescription();
            $invoice->ensureTypeMatchesGeneralDescription();
            $invoice->ensureOnlyPurchaseInvoiceHasSupplier();
            $invoice->ensureOnlyPurchaseInvoiceHasContainer();
            $invoice->ensureOnlyPurchaseInvoiceHasCategory();
            $invoice->ensurePurchaseInvoiceHasCbmPrice();
            $invoice->ensureCbmPriceIsNotLocked();
            $invoice->lockCbmPriceInContainer();
            $invoice->applyPaymentAmounts();
        });

        static::creating(function (Invoice $invoice): void {
            $invoice->namePurchaseInvoiceAfterSupplier();
            $invoice->startPurchaseInvoiceInProduction();
        });

        // Changing the price of one CBM or the dinar rate saves the lines again, so they keep their costs at the new rates.
        static::saving(function (Invoice $invoice): void {
            $invoice->costRatesChanged = $invoice->exists && $invoice->isDirty(['cbm_price', 'dinar_rate']);
        });

        static::saved(function (Invoice $invoice): void {
            if (! $invoice->costRatesChanged) {
                return;
            }

            // The original is synced only after this event, so without this the saves below would see the change again.
            $invoice->costRatesChanged = false;
            $invoice->syncOriginalAttributes(['cbm_price', 'dinar_rate']);
            $invoice->items()->each(fn (InvoiceItem $line) => $line->save());
            $invoice->recalculateAmounts();
        });

        static::deleting(function (Invoice $invoice): void {
            $invoice->ensureHasNoPayoffs();
            $invoice->ensureHasNoCustomerPayment();
        });

        static::deleted(function (Invoice $invoice): void {
            $invoice->payoffs()->where('is_initial_payment', true)->each(fn (Payoff $payoff) => $payoff->deleteWithItsInvoice());
        });

        static::restored(function (Invoice $invoice): void {
            $invoice->payoffs()->onlyTrashed()->where('is_initial_payment', true)->each(fn (Payoff $payoff) => $payoff->restore());
        });
    }

    /**
     * Whether this is a purchase invoice: its general description is the purchases description.
     */
    public function isPurchase(): bool
    {
        return $this->generalDescription()->withTrashed()->first()?->is_purchases_description === true;
    }

    /**
     * Moves this purchase invoice to its next stage, one step forward.
     *
     * @throws ValidationException when it is not a purchase invoice, or it is already at the last stage.
     */
    public function advanceStage(): void
    {
        $next = $this->stage?->next();

        if ($next === null) {
            throw ValidationException::withMessages([
                'stage' => __('invoices.validation.stage_cannot_advance'),
            ]);
        }

        $this->stage = $next;
        $this->save();
    }

    /**
     * Marks this purchase invoice as received, straight from whatever stage it is at. Scanning a carton label is the proof
     * that the goods arrived, so unlike advanceStage() it does not go one step at a time. An invoice already received stays as it is.
     */
    public function receive(): void
    {
        if ($this->stage === InvoiceStage::Received) {
            return;
        }

        $this->stage = InvoiceStage::Received;
        $this->save();
    }

    /**
     * The side that still has to confirm this purchase invoice, or null when both sides confirmed it.
     */
    public function awaitedConfirmation(): ?InvoiceConfirmation
    {
        return match ($this->status) {
            InvoiceStatus::Pending => InvoiceConfirmation::Importer,
            InvoiceStatus::ImporterConfirmed => InvoiceConfirmation::Receiver,
            default => null,
        };
    }

    /**
     * Whether this invoice moves money out of a sales box: one of its lines is under a description that has a transfer type.
     * Such an invoice is created only by a cashier money transfer or a customer payment, so it is read only on the screens.
     */
    public function isMoneyTransfer(): bool
    {
        return $this->items()
            ->withTrashed()
            ->whereHas('specificDescription', fn (Builder $query): Builder => $query->withTrashed()->whereNotNull('transfer_type'))
            ->exists();
    }

    /**
     * The expenses: all output invoices, purchase invoices included. This is the only definition of an expense.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expenses(Builder $query): void
    {
        $query->where('type', InvoiceType::Output);
    }

    /**
     * The general expenses: the expenses without the purchase invoices.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function generalExpenses(Builder $query): void
    {
        $query
            ->expenses()
            ->where('general_description_id', '!=', GeneralDescription::purchases()->id);
    }

    /**
     * The purchase expenses: the expenses that are purchase invoices.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function purchaseExpenses(Builder $query): void
    {
        $query
            ->expenses()
            ->purchases();
    }

    /**
     * The revenues: input invoices. This is the only definition of a revenue.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function revenues(Builder $query): void
    {
        $query->where('type', InvoiceType::Input);
    }

    /**
     * The invoices under a capital movement description, in both directions: the money the partners put in,
     * and the money they take out. It is not a profit and not an expense, so the profit and loss report keeps it
     * out of both sides and shows it on its own. This is the only definition of a capital movement invoice.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function capitalMovements(Builder $query): void
    {
        $query->whereHas('generalDescription', fn (Builder $query): Builder => $query->withTrashed()->capitalMovements());
    }

    /**
     * The invoices that only move money between the boxes: one of their lines is under a description that has a transfer type,
     * such as the cashier money transfer and the customer payment. The money is already in the sales, so it is not a revenue.
     * This is the only definition of a money transfer invoice in queries.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function moneyTransfers(Builder $query): void
    {
        $query->whereHas('items', fn (Builder $query): Builder => $query
            ->withTrashed()
            ->whereHas('specificDescription', fn (Builder $query): Builder => $query->withTrashed()->whereNotNull('transfer_type')));
    }

    /**
     * The external revenues: the revenues that are a profit of their own, next to the profit of the sales.
     * They are the input invoices without the money transfers, whose money comes from the sales and is counted there,
     * and without the capital movements, which are not earned. This is the only definition of an external revenue.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function externalRevenues(Builder $query): void
    {
        $query
            ->revenues()
            ->whereDoesntHave('generalDescription', fn (Builder $query): Builder => $query->withTrashed()->capitalMovements())
            ->whereDoesntHave('items', fn (Builder $query): Builder => $query
                ->withTrashed()
                ->whereHas('specificDescription', fn (Builder $query): Builder => $query->withTrashed()->whereNotNull('transfer_type')));
    }

    /**
     * The operating expenses: the expenses that lower the profit. They are the general expenses without the capital movements,
     * because taking capital out is not a cost of the work. The purchase invoices are already out of the general expenses:
     * buying goods only turns money into stock. This is the only definition of an operating expense.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function operatingExpenses(Builder $query): void
    {
        $query
            ->generalExpenses()
            ->whereDoesntHave('generalDescription', fn (Builder $query): Builder => $query->withTrashed()->capitalMovements());
    }

    /**
     * The purchase invoices: invoices under the purchases description. This is the only definition of a purchase invoice in queries.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function purchases(Builder $query): void
    {
        $query->where('general_description_id', GeneralDescription::purchases()->id);
    }

    /**
     * The debts: purchase invoices that still have a remaining. This is the only definition of a debt.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function debts(Builder $query): void
    {
        $query
            ->purchases()
            ->where('remaining', '>', 0);
    }

    /**
     * The total debt to the suppliers: the sum of the remaining of the debts.
     */
    public static function totalDebt(): float
    {
        return (float) static::query()->debts()->sum('remaining');
    }

    /**
     * The debt to each supplier, from the largest to the smallest: the largest suppliers by name and debt,
     * and the sum of the debts of the rest as others.
     *
     * @return array{suppliers: list<array{name: string, debt: float}>, others: float}
     */
    public static function debtBySupplier(int $top = 5): array
    {
        return static::sumBySupplier(static::query()->debts(), 'remaining', 'debt', $top);
    }

    /**
     * The number of purchase invoices waiting for this side to confirm them: the pending ones wait for the importer,
     * and the ones the importer confirmed wait for the receiver. Deleted invoices are not counted.
     */
    public static function purchasesAwaitingCount(InvoiceConfirmation $side): int
    {
        return static::query()->purchases()->where('status', $side->requiredStatus())->count();
    }

    /**
     * The purchases from each supplier, from the largest to the smallest: the largest suppliers by name and the sum of their invoice amounts,
     * and the sum of the purchases from the rest as others.
     *
     * @return array{suppliers: list<array{name: string, amount: float}>, others: float}
     */
    public static function purchasesBySupplier(int $top = 5): array
    {
        return static::sumBySupplier(static::query()->purchases(), 'amount', 'amount', $top);
    }

    /**
     * Sums a column of the invoices of the query for each supplier, from the largest sum to the smallest:
     * the largest suppliers by name, with their sum under the given key, and the sum of the rest as others.
     *
     * @param  Builder<self>  $query
     * @return array{suppliers: list<array<string, string|float>>, others: float}
     */
    /**
     * Where the general expenses went: the descriptions spent on the most, from the largest to the smallest,
     * and the sum of the rest as one part. It reads the general expenses only, so the purchases have no part in it,
     * and it sums what was actually paid, which for a general invoice is its whole amount.
     *
     * @return array{descriptions: list<array{name: string, expense: float}>, others: float}
     */
    public static function generalExpensesByDescription(int $top = 5): array
    {
        $rows = static::query()
            ->generalExpenses()
            ->selectRaw('general_description_id, sum(paid_amount) as total')
            ->groupBy('general_description_id')
            ->orderByDesc('total')
            ->orderBy('general_description_id')
            ->get();

        $names = GeneralDescription::withTrashed()
            ->whereIn('id', $rows->pluck('general_description_id'))
            ->pluck('name', 'id');

        return [
            'descriptions' => $rows->take($top)
                ->map(fn (Invoice $row): array => [
                    'name' => $names[$row->general_description_id],
                    'expense' => (float) $row->total,
                ])
                ->values()
                ->all(),
            'others' => (float) $rows->slice($top)->sum('total'),
        ];
    }

    private static function sumBySupplier(Builder $query, string $column, string $key, int $top): array
    {
        $rows = $query
            ->selectRaw("supplier_id, sum({$column}) as total")
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->orderBy('supplier_id')
            ->get();

        $names = Supplier::withTrashed()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name_ar', 'id');

        return [
            'suppliers' => $rows->take($top)
                ->map(fn (Invoice $row): array => ['name' => $names[$row->supplier_id], $key => (float) $row->total])
                ->values()
                ->all(),
            'others' => (float) $rows->slice($top)->sum('total'),
        ];
    }

    /**
     * The accountant's box inside the period: what was paid on the revenues minus what was paid on the expenses.
     * This is the only definition of the accountant's box.
     */
    public static function accountantBalance(Period $period): float
    {
        $revenues = (float) $period->applyTo(static::query()->revenues(), 'date')->sum('paid_amount');
        $expenses = (float) $period->applyTo(static::query()->expenses(), 'date')->sum('paid_amount');

        return $revenues - $expenses;
    }

    /**
     * What the company owed its suppliers at the end of that day: the remaining of the purchase invoices dated up to it.
     * What was paid on an invoice is counted on the invoice's own date, exactly like the accountant's box counts it,
     * so the money that left the box and the debt that went down always agree.
     * Unlike the total debt, this looks back in time and counts every purchase invoice, even one that is fully paid,
     * and a return invoice lowers the balance because its remaining is negative.
     * This is the only definition of what was owed to the suppliers at a moment.
     */
    public static function supplierBalanceAt(CarbonImmutable $moment): float
    {
        return (float) (new Period(null, $moment))
            ->applyTo(static::query()->purchases(), 'date')
            ->sum('remaining');
    }

    /**
     * The goods that were bought and not in the stock yet at the end of that day: the amounts of the purchase invoices
     * dated up to it that both sides did not confirm. The company already owes them, and their goods enter the stock
     * only when both sides confirm, so they are held here at what they were bought for.
     * This is the only definition of the purchases waiting for a confirmation at a moment.
     */
    public static function unconfirmedPurchasesAt(CarbonImmutable $moment): float
    {
        return (float) (new Period(null, $moment))
            ->applyTo(static::query()->purchases()->where('status', '!=', InvoiceStatus::Confirmed), 'date')
            ->sum('amount');
    }

    /**
     * The purchases of the period: the sum of the amounts of the purchase invoices dated inside it, whatever their
     * confirmation, paid or still owed. Deleted invoices are not counted. This is the only definition of the purchases of a period.
     */
    public static function purchasesAmount(Period $period): float
    {
        return (float) $period->applyTo(static::query()->purchases(), 'date')->sum('amount');
    }

    /**
     * The capital the partners moved inside the period: what they put in minus what they took out.
     * It is not a profit and not an expense, so it is what explains a change in the value of the company
     * that the profit does not explain. This is the only definition of the capital movement of a period.
     */
    public static function capitalMovement(Period $period): float
    {
        $putIn = (float) $period->applyTo(static::query()->capitalMovements()->revenues(), 'date')->sum('paid_amount');
        $takenOut = (float) $period->applyTo(static::query()->capitalMovements()->expenses(), 'date')->sum('paid_amount');

        return $putIn - $takenOut;
    }

    /**
     * Sets the amount to the sum of the line prices, and the CBM to the sum of the lines' CBM, and saves the invoice.
     * The amount in US dollars is the sum of the lines' prices in dollars (none without an exchange rate), and the transport cost
     * in US dollars the sum of the lines' transport costs (none without a price of one CBM).
     * The amount and the transport cost in Iraqi dinars are the sums of the lines' (none without both rates).
     * A deleted invoice keeps its amount and its CBM.
     */
    public function recalculateAmounts(): void
    {
        if ($this->trashed()) {
            return;
        }

        $this->amount = (float) $this->items()->sum('price');
        $this->cbm = (float) $this->items()->sum('cbm');
        $this->amount_usd = $this->exchange_rate > 0 ? (float) $this->items()->sum('price_usd') : null;
        $this->transport_cost_usd = $this->cbm_price !== null ? (float) $this->items()->sum('transport_cost_usd') : null;
        $hasDinarCosts = $this->exchange_rate > 0 && $this->dinarsPerDollar() !== null;
        $this->amount_iqd = $hasDinarCosts ? (float) $this->items()->sum('price_iqd') : null;
        $this->transport_cost_iqd = $hasDinarCosts && $this->cbm_price !== null ? (float) $this->items()->sum('transport_cost_iqd') : null;
        $this->save();
    }

    /**
     * The Iraqi dinars in one US dollar. The dinar rate is written as the market writes it, in thousands of dinars for 100 dollars:
     * 170 means 100 $ = 170,000 dinars, so one dollar is 1,700 dinars. Null without a dinar rate.
     */
    public function dinarsPerDollar(): ?float
    {
        return $this->dinar_rate > 0 ? $this->dinar_rate * 1000 / 100 : null;
    }

    /**
     * The lines of this invoice.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * The payoffs of this purchase invoice.
     *
     * @return HasMany<Payoff, $this>
     */
    public function payoffs(): HasMany
    {
        return $this->hasMany(Payoff::class);
    }

    /**
     * The customer payment this invoice was created with, when it is a postpaid money withdrawal of a payment.
     *
     * @return HasOne<CustomerPayment, $this>
     */
    public function customerPayment(): HasOne
    {
        return $this->hasOne(CustomerPayment::class);
    }

    /**
     * The general description of this invoice.
     *
     * @return BelongsTo<GeneralDescription, $this>
     */
    public function generalDescription(): BelongsTo
    {
        return $this->belongsTo(GeneralDescription::class);
    }

    /**
     * The supplier of this invoice.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The category of this purchase invoice, such as frames or lenses. A general invoice has none.
     *
     * @return BelongsTo<InvoiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class, 'invoice_category_id');
    }

    /**
     * The container this purchase invoice is shipped in, if any.
     *
     * @return BelongsTo<Container, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /**
     * The user who created this invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The importer who confirmed this purchase invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function importerChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importer_checker_id');
    }

    /**
     * The receiver who confirmed this purchase invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiverChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_checker_id');
    }

    /**
     * The cashier of this general invoice, such as the cashier the money was received from.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * The original invoice of a return invoice.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    /**
     * The purchase invoice this one was split off from, when only part of that invoice was shipped in a container.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'split_from_invoice_id');
    }

    /**
     * The parts split off this purchase invoice into containers.
     *
     * @return HasMany<Invoice, $this>
     */
    public function splitParts(): HasMany
    {
        return $this->hasMany(Invoice::class, 'split_from_invoice_id');
    }

    /**
     * A purchase invoice cannot be moved to another general description after it is created.
     *
     * @throws ValidationException
     */
    protected function ensurePurchaseInvoiceKeepsItsGeneralDescription(): void
    {
        if (! $this->exists || ! $this->isDirty('general_description_id')) {
            return;
        }

        $originalDescription = GeneralDescription::withTrashed()->find($this->getOriginal('general_description_id'));

        if ($originalDescription?->is_purchases_description === true) {
            throw ValidationException::withMessages([
                'general_description_id' => __('invoices.validation.purchase_invoice_general_description_cannot_change'),
            ]);
        }
    }

    /**
     * The invoice type (input or output) must be the type of its general description.
     *
     * @throws ValidationException
     */
    protected function ensureTypeMatchesGeneralDescription(): void
    {
        $description = GeneralDescription::withTrashed()->findOrFail($this->general_description_id);

        if ($description->type !== $this->type) {
            throw ValidationException::withMessages([
                'general_description_id' => __('invoices.validation.type_not_matching_general_description'),
            ]);
        }
    }

    /**
     * A purchase invoice must have a supplier, and a general invoice must have none.
     *
     * @throws ValidationException
     */
    protected function ensureOnlyPurchaseInvoiceHasSupplier(): void
    {
        if ($this->isPurchase() && $this->supplier_id === null) {
            throw ValidationException::withMessages([
                'supplier_id' => __('invoices.validation.purchase_invoice_needs_supplier'),
            ]);
        }

        if (! $this->isPurchase() && $this->supplier_id !== null) {
            throw ValidationException::withMessages([
                'supplier_id' => __('invoices.validation.general_invoice_has_no_supplier'),
            ]);
        }
    }

    /**
     * A new purchase invoice is named after its supplier and the supplier's counter, such as "علي 3" for his third purchase invoice.
     * Deleted invoices are counted too, so a number is never used twice. Parts split off an invoice are not counted, because they are named after it.
     * The name is set once and stays when the invoice is edited.
     */
    protected function namePurchaseInvoiceAfterSupplier(): void
    {
        if ($this->supplier_id === null || ! $this->isPurchase()) {
            return;
        }

        $supplier = Supplier::withTrashed()->findOrFail($this->supplier_id);
        $counter = static::withTrashed()->where('supplier_id', $supplier->id)->whereNull('split_from_invoice_id')->count() + 1;

        $this->name = "{$supplier->name_ar} {$counter}";
    }

    /**
     * A new purchase invoice starts in production. A general invoice has no stage.
     */
    protected function startPurchaseInvoiceInProduction(): void
    {
        if ($this->isPurchase()) {
            $this->stage = InvoiceStage::InProduction;
        }
    }

    /**
     * Only a purchase invoice can be in a container.
     *
     * @throws ValidationException
     */
    protected function ensureOnlyPurchaseInvoiceHasContainer(): void
    {
        if ($this->container_id !== null && ! $this->isPurchase()) {
            throw ValidationException::withMessages([
                'container_id' => __('invoices.validation.general_invoice_has_no_container'),
            ]);
        }
    }

    /**
     * A purchase invoice needs its price of one CBM in US dollars, which its transport cost is calculated from.
     *
     * @throws ValidationException
     */
    protected function ensurePurchaseInvoiceHasCbmPrice(): void
    {
        if ($this->cbm_price === null && $this->isPurchase()) {
            throw ValidationException::withMessages([
                'cbm_price' => __('invoices.validation.purchase_invoice_needs_cbm_price'),
            ]);
        }
    }

    /**
     * Once the invoice has entered a container, its price of one CBM cannot be changed, even after it leaves the container.
     *
     * @throws ValidationException
     */
    protected function ensureCbmPriceIsNotLocked(): void
    {
        if ($this->exists && $this->isDirty('cbm_price') && $this->getOriginal('cbm_price_locked_at') !== null) {
            throw ValidationException::withMessages([
                'cbm_price' => __('invoices.validation.cbm_price_locked'),
            ]);
        }
    }

    /**
     * The first time the invoice is in a container, its price of one CBM is locked for good.
     */
    protected function lockCbmPriceInContainer(): void
    {
        if ($this->container_id !== null && $this->cbm_price_locked_at === null) {
            $this->cbm_price_locked_at = now();
        }
    }

    /**
     * Only a purchase invoice has a category.
     *
     * @throws ValidationException
     */
    protected function ensureOnlyPurchaseInvoiceHasCategory(): void
    {
        if ($this->invoice_category_id !== null && ! $this->isPurchase()) {
            throw ValidationException::withMessages([
                'invoice_category_id' => __('invoices.validation.general_invoice_has_no_category'),
            ]);
        }
    }

    /**
     * An invoice that has payoffs cannot be deleted.
     *
     * @throws ValidationException
     */
    protected function ensureHasNoPayoffs(): void
    {
        if ($this->payoffs()->where('is_initial_payment', false)->exists()) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.invoice_has_payoffs'),
            ]);
        }
    }

    /**
     * The invoice of a customer payment is deleted only by deleting the payment, which deletes the payment first.
     *
     * @throws ValidationException
     */
    protected function ensureHasNoCustomerPayment(): void
    {
        if ($this->customerPayment()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.invoice_has_customer_payment'),
            ]);
        }
    }

    /**
     * A purchase invoice is a debt: its payoffs are paid, and the rest remains. A general invoice is always fully paid.
     */
    protected function applyPaymentAmounts(): void
    {
        $amount = (float) ($this->amount ?? 0);

        if ($this->isPurchase()) {
            $paidAmount = $this->exists ? (float) $this->payoffs()->sum('amount') : 0.0;

            $this->paid_amount = $paidAmount;
            $this->remaining = $amount - $paidAmount;

            return;
        }

        $this->paid_amount = $amount;
        $this->remaining = 0;
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
            'status' => InvoiceStatus::class,
            'stage' => InvoiceStage::class,
            'date' => 'date',
            'importer_confirmed_at' => 'datetime',
            'receiver_confirmed_at' => 'datetime',
            'amount' => 'float',
            'paid_amount' => 'float',
            'remaining' => 'float',
            'cbm' => 'float',
            'cbm_price' => 'float',
            'cbm_price_locked_at' => 'datetime',
            'exchange_rate' => 'float',
            'amount_usd' => 'float',
            'amount_iqd' => 'float',
            'dinar_rate' => 'float',
            'transport_cost_iqd' => 'float',
            'transport_cost_usd' => 'float',
            'exchange_rate_source' => ExchangeRateSource::class,
            'profit_difference' => 'float',
            'images' => 'array',
        ];
    }
}
