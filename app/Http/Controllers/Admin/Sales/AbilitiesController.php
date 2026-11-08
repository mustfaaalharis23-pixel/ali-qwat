<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Filament\Pages\InvoicePrinting;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Sales\SalesPayload;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * What the signed-in user may do on the sales screens, read once by the browser to show or hide columns and buttons
 * (such as the cost and profit columns) before a list answers.
 */
class AbilitiesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'see_cost' => SalesPayload::seesCost(),
            'create_order' => Gate::allows('create', Order::class),
            'create_customer' => Gate::allows('create', Customer::class),
            'view_payments' => Gate::allows('viewAny', CustomerPayment::class),
            'create_payment' => Gate::allows('create', CustomerPayment::class),
            'print_invoices' => Gate::allows('viewAny', InvoicePrinting::class),
        ]);
    }
}
