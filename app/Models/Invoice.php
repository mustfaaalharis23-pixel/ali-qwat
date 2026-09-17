<?php

namespace App\Models;

use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Metrics\Period;
use App\Observers\StockObserver;
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
    'user_id',
    'cashier_id',
    'date',
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
     * Before every save, wherever it is saved from, a purchase invoice keeps its general description,
     * the invoice must have the type of its general description, only a purchase invoice has a supplier,
     * and its paid amount and remaining are set from its amount and payoffs.
     * An invoice that has payoffs, or that belongs to a customer payment, cannot be deleted.
     */
    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice): void {
            $invoice->ensurePurchaseInvoiceKeepsItsGeneralDescription();
            $invoice->ensureTypeMatchesGeneralDescription();
            $invoice->ensureOnlyPurchaseInvoiceHasSupplier();
            $invoice->applyPaymentAmounts();
        });

        static::deleting(function (Invoice $invoice): void {
            $invoice->ensureHasNoPayoffs();
            $invoice->ensureHasNoCustomerPayment();
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
     * Sets the amount to the sum of the line prices and saves the invoice. A deleted invoice keeps its amount.
     */
    public function recalculateAmounts(): void
    {
        if ($this->trashed()) {
            return;
        }

        $this->amount = (float) $this->items()->sum('price');
        $this->save();
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
     * An invoice that has payoffs cannot be deleted.
     *
     * @throws ValidationException
     */
    protected function ensureHasNoPayoffs(): void
    {
        if ($this->payoffs()->exists()) {
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
            'date' => 'date',
            'importer_confirmed_at' => 'datetime',
            'receiver_confirmed_at' => 'datetime',
            'amount' => 'float',
            'paid_amount' => 'float',
            'remaining' => 'float',
            'profit_difference' => 'float',
            'images' => 'array',
        ];
    }
}
