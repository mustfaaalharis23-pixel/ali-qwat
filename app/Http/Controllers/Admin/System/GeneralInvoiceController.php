<?php

namespace App\Http\Controllers\Admin\System;

use App\Enums\InvoiceType;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\System\EnumValue;
use App\Http\Resources\Admin\System\GeneralInvoiceResource;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SpecificDescription;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The general invoices: the invoices whose general description is not the purchases description.
 * Money is never withdrawn from a sales box by hand here: a general description that holds transfer descriptions cannot be saved,
 * and an invoice that moves money (a cashier money transfer or a customer payment) is read only.
 * The amount is the model's: the sum of the lines' prices, and a general invoice is always fully paid.
 */
class GeneralInvoiceController extends Controller
{
    use ListsRecords;

    /**
     * The colors of the expenses chart, from the largest expense to the smallest, and the gray of the rest.
     *
     * @var list<string>
     */
    private const CHART_COLORS = ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'];

    private const OTHERS_COLOR = '#9ca3af';

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = $this->listRecords(
            Invoice::withMoneyTransferFlag($this->generalInvoices())->with($this->relations()),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'date' => 'date', 'amount' => 'amount'],
            filters: [
                'type' => fn (Builder $query, string $value) => $query->where('type', $value),
                'general_description_id' => fn (Builder $query, string $value) => $query->where('general_description_id', (int) $value),
            ],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return GeneralInvoiceResource::collection($invoices)->additional([
            'can' => ['create' => Gate::allows('create', Invoice::class)],
            'types' => EnumValue::options(InvoiceType::class),
        ]);
    }

    public function show(int $invoice): GeneralInvoiceResource
    {
        $record = $this->find($invoice);
        Gate::authorize('view', $record);

        return new GeneralInvoiceResource($record);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        $data = $this->validated($request);

        $invoice = DB::transaction(function () use ($data, $request): Invoice {
            $invoice = Invoice::query()->create([...$this->invoiceFields($data), 'user_id' => $request->user()->id]);

            foreach ($data['items'] as $line) {
                $invoice->items()->create(['specific_description_id' => $line['specific_description_id'], 'price' => $line['price']]);
            }

            return $invoice;
        });

        return (new GeneralInvoiceResource($this->find($invoice->id)))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $invoice): GeneralInvoiceResource
    {
        $record = $this->find($invoice);
        Gate::authorize('update', $record);
        abort_if($record->isMoneyTransfer(), 403);

        $data = $this->validated($request, $record);

        DB::transaction(function () use ($record, $data): void {
            $record->update($this->invoiceFields($data));

            $keptIds = collect($data['items'])->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
            $record->items()->whereNotIn('id', $keptIds)->get()->each(fn (InvoiceItem $line): ?bool => $line->delete());

            foreach ($data['items'] as $line) {
                $fields = ['specific_description_id' => $line['specific_description_id'], 'price' => $line['price']];

                if (! empty($line['id'])) {
                    $record->items()->findOrFail($line['id'])->update($fields);
                } else {
                    $record->items()->create($fields);
                }
            }
        });

        return new GeneralInvoiceResource($this->find($record->id));
    }

    public function destroy(int $invoice): JsonResponse
    {
        $record = $this->find($invoice);
        Gate::authorize('delete', $record);
        abort_if($record->isMoneyTransfer(), 403);

        $record->delete();

        return response()->json(status: 204);
    }

    public function restore(Request $request, int $invoice): GeneralInvoiceResource
    {
        $record = $this->find($invoice);
        abort_unless($record->trashed() && $request->user()->is_super === true, 403);
        Gate::authorize('restore', $record);

        $record->restore();

        return new GeneralInvoiceResource($this->find($record->id));
    }

    public function forceDelete(int $invoice): JsonResponse
    {
        $record = $this->find($invoice);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * Stores one image of an invoice on the public disk, before the invoice is saved, and answers its path to send with the invoice.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        abort_unless(Gate::allows('create', Invoice::class) || $request->user()->is_super === true, 403);

        $request->validate([
            'image' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ], attributes: ['image' => __('filament/resources/invoice.fields.images')]);

        $path = $request->file('image')->store('invoices', 'public');

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    /**
     * The pie of where the general expenses went: the descriptions spent on the most, then the rest as one gray part.
     */
    public function expensesChart(): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $expenses = Invoice::generalExpensesByDescription();

        $slices = collect($expenses['descriptions'])
            ->values()
            ->map(fn (array $description, int $index): array => [
                'name' => $description['name'],
                'value' => $description['expense'],
                'color' => self::CHART_COLORS[$index] ?? self::OTHERS_COLOR,
            ])
            ->all();

        if ($expenses['others'] > 0) {
            $slices[] = [
                'name' => __('filament/resources/general_invoice.widgets.expenses_chart.others'),
                'value' => $expenses['others'],
                'color' => self::OTHERS_COLOR,
            ];
        }

        return response()->json([
            'heading' => __('filament/resources/general_invoice.widgets.expenses_chart.heading'),
            'slices' => $slices,
        ]);
    }

    /**
     * @return Builder<Invoice>
     */
    private function generalInvoices(): Builder
    {
        return Invoice::query()->whereHas('generalDescription', fn (Builder $query): Builder => $query->where('is_purchases_description', false));
    }

    /**
     * @return array<string, Closure>
     */
    private function relations(): array
    {
        return [
            'generalDescription' => fn ($query) => $query->withTrashed(),
            'cashier' => fn ($query) => $query->withTrashed(),
            'user' => fn ($query) => $query->withTrashed(),
        ];
    }

    /**
     * A general invoice with its lines, deleted or not; a purchase invoice is not found here.
     */
    private function find(int $id): Invoice
    {
        return Invoice::withMoneyTransferFlag($this->generalInvoices()->withTrashed())
            ->with([...$this->relations(), 'items' => fn ($query) => $query->orderBy('id')->with(['specificDescription' => fn ($query) => $query->withTrashed()])])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function invoiceFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'date' => $data['date'],
            'type' => $data['type'],
            'general_description_id' => $data['general_description_id'],
            'images' => array_values($data['images'] ?? []),
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * A price is accepted as the money field writes it, with thousands separators.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Invoice $invoice = null): array
    {
        $request->merge([
            'items' => collect((array) $request->input('items', []))
                ->map(fn (mixed $line): mixed => is_array($line) && isset($line['price']) && is_string($line['price'])
                    ? [...$line, 'price' => str_replace(',', '', $line['price'])]
                    : $line)
                ->all(),
        ]);

        $generalDescriptionId = $request->integer('general_description_id');

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::enum(InvoiceType::class)],
            'general_description_id' => [
                'required',
                'integer',
                Rule::exists(GeneralDescription::class, 'id')
                    ->where(fn ($query) => $query->where('is_purchases_description', false))
                    ->where('type', (string) $request->input('type'))
                    ->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (GeneralDescription::query()->find($value)?->holdsTransferDescriptions() === true) {
                        $fail(__('filament/resources/general_invoice.money_transfer_notice.cannot_save'));
                    }
                },
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', Rule::exists(InvoiceItem::class, 'id')->where('invoice_id', $invoice?->id ?? 0)->whereNull('deleted_at')],
            'items.*.specific_description_id' => [
                'required',
                'integer',
                Rule::exists(SpecificDescription::class, 'id')->where('general_description_id', $generalDescriptionId)->whereNull('deleted_at'),
            ],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'images' => ['nullable', 'array'],
            'images.*' => [
                'bail',
                'string',
                'starts_with:invoices/',
                'not_regex:/\.\.|\\\\/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! Storage::disk('public')->exists($value)) {
                        $fail(__('validation.exists', ['attribute' => __('filament/resources/invoice.fields.images')]));
                    }
                },
            ],
            'notes' => ['nullable', 'string'],
        ], attributes: [
            'name' => __('filament/resources/invoice.fields.name'),
            'date' => __('filament/resources/invoice.fields.date'),
            'type' => __('filament/resources/invoice.fields.type'),
            'general_description_id' => __('filament/resources/invoice.fields.general_description_id'),
            'items' => __('filament/resources/general_invoice.fields.items'),
            'items.*.specific_description_id' => __('filament/resources/invoice_item.fields.specific_description_id'),
            'items.*.price' => __('filament/resources/invoice_item.fields.price'),
            'images' => __('filament/resources/invoice.fields.images'),
            'images.*' => __('filament/resources/invoice.fields.images'),
            'notes' => __('filament/resources/invoice.fields.notes'),
        ]);
    }
}
