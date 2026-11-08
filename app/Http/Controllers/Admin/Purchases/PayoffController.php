<?php

namespace App\Http\Controllers\Admin\Purchases;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Purchases\PayoffResource;
use App\Models\Invoice;
use App\Models\Payoff;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The payoffs of a purchase invoice, managed from the invoice's page: added, changed, deleted, and restored with the payoff permissions.
 * A payoff is at most what remains on the invoice (plus its own amount when it is changed); the initial payment is never changed
 * or deleted on its own. The model keeps every other rule and recalculates the invoice.
 */
class PayoffController extends Controller
{
    use ListsRecords;

    public function index(Request $request, int $invoice): AnonymousResourceCollection
    {
        $record = Invoice::withTrashed()->purchases()->findOrFail($invoice);
        Gate::authorize('viewAny', Payoff::class);

        $payoffs = $this->listRecords(
            $record->payoffs()->getQuery(),
            $request,
            sortable: ['id' => 'id', 'date' => 'date', 'amount' => 'amount'],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return PayoffResource::collection($payoffs)->additional([
            'can' => ['create' => ! $record->trashed() && Gate::allows('create', Payoff::class)],
            'remaining' => (float) $record->remaining,
        ]);
    }

    public function store(Request $request, int $invoice): JsonResponse
    {
        $record = Invoice::query()->purchases()->findOrFail($invoice);
        Gate::authorize('create', Payoff::class);

        $payoff = Payoff::query()->create([
            ...$this->validated($request, round((float) $record->remaining, 2)),
            'invoice_id' => $record->id,
        ]);

        return (new PayoffResource($payoff))->response()->setStatusCode(201);
    }

    public function update(Request $request, Payoff $payoff): PayoffResource
    {
        Gate::authorize('update', $payoff);
        abort_if($payoff->is_initial_payment, 403);

        $invoice = Invoice::withTrashed()->findOrFail($payoff->invoice_id);
        $payoff->update($this->validated($request, round((float) $invoice->remaining + (float) $payoff->amount, 2)));

        return new PayoffResource($payoff);
    }

    public function destroy(Payoff $payoff): JsonResponse
    {
        Gate::authorize('delete', $payoff);
        abort_if($payoff->is_initial_payment, 403);

        $payoff->delete();

        return response()->json(status: 204);
    }

    public function restore(int $payoff): PayoffResource
    {
        $record = Payoff::onlyTrashed()->findOrFail($payoff);
        Gate::authorize('restore', $record);

        $record->restore();

        return new PayoffResource($record);
    }

    public function forceDelete(int $payoff): JsonResponse
    {
        $record = Payoff::withTrashed()->findOrFail($payoff);
        Gate::authorize('forceDelete', $record);
        abort_if($record->is_initial_payment, 403);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * @return array{amount: float, date: string, image?: ?string}
     */
    private function validated(Request $request, float $maximum): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$maximum}"],
            'date' => ['required', 'date'],
            'image' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! preg_match('#^payoffs/[A-Za-z0-9._-]+$#', (string) $value) || ! Storage::disk('public')->exists((string) $value)) {
                    $fail(__('validation.exists', ['attribute' => __('filament/resources/payoff.fields.image')]));
                }
            }],
        ], attributes: [
            'amount' => __('filament/resources/payoff.fields.amount'),
            'date' => __('filament/resources/payoff.fields.date'),
            'image' => __('filament/resources/payoff.fields.image'),
        ]);
    }
}
