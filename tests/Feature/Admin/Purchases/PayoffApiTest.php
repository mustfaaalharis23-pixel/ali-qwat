<?php

namespace Tests\Feature\Admin\Purchases;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payoff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayoffApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);
        }

        return $user;
    }

    private function invoiceOf(float $amount): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => 1, 'pieces_per_packet' => 1, 'boxes_per_carton' => 1, 'carton_cbm' => 0.05, 'unit_cost' => $amount, 'price' => $amount]);

        return $invoice->fresh();
    }

    /**
     * EN: A payoff is added to an invoice, its paid amount and remaining follow, and a payoff above the remaining is refused.
     * AR: التسديد ينضاف للفاتورة، والمدفوع والباقي يتبعونه، والتسديد الأكثر من الباقي ينرفض.
     */
    public function test_a_payoff_is_added_up_to_the_remaining(): void
    {
        $invoice = $this->invoiceOf(1000);
        $this->actingAs($this->userWith('payoff.view', 'payoff.create'));

        $this->postJson("/admin-api/purchase-invoices/{$invoice->id}/payoffs", ['amount' => 400, 'date' => '2026-11-01'])->assertCreated();
        $this->assertEqualsWithDelta(600, $invoice->fresh()->remaining, 0.001);

        $this->postJson("/admin-api/purchase-invoices/{$invoice->id}/payoffs", ['amount' => 700, 'date' => '2026-11-01'])->assertJsonValidationErrors('amount');
        $this->getJson("/admin-api/purchase-invoices/{$invoice->id}/payoffs")->assertOk()->assertJsonCount(1, 'data');
    }

    /**
     * EN: The initial payment can be neither changed nor deleted on its own, and its row says so.
     * AR: الدفعة الأولى ما تتغير ولا تنحذف وحدها، وصفها يگول هالشي.
     */
    public function test_the_initial_payment_is_locked(): void
    {
        $invoice = $this->invoiceOf(1000);
        $payoff = Payoff::query()->create(['invoice_id' => $invoice->id, 'amount' => 100, 'date' => today(), 'is_initial_payment' => true]);
        $this->actingAs($this->userWith('payoff.view', 'payoff.update', 'payoff.delete'));

        $this->getJson("/admin-api/purchase-invoices/{$invoice->id}/payoffs")->assertJsonPath('data.0.can.update', false)->assertJsonPath('data.0.can.delete', false);
        $this->putJson("/admin-api/payoffs/{$payoff->id}", ['amount' => 50, 'date' => '2026-11-01'])->assertForbidden();
        $this->deleteJson("/admin-api/payoffs/{$payoff->id}")->assertForbidden();
    }

    /**
     * EN: A payoff is edited and deleted with the permissions, and the invoice follows.
     * AR: التسديد يتعدل وينحذف ويا الصلاحيات، والفاتورة تتبعه.
     */
    public function test_a_payoff_is_edited_and_deleted(): void
    {
        $invoice = $this->invoiceOf(1000);
        $payoff = Payoff::query()->create(['invoice_id' => $invoice->id, 'amount' => 100, 'date' => today()]);
        $this->actingAs($this->userWith('payoff.view', 'payoff.update', 'payoff.delete'));

        $this->putJson("/admin-api/payoffs/{$payoff->id}", ['amount' => 1000, 'date' => '2026-11-01'])->assertOk();
        $this->assertEqualsWithDelta(0, $invoice->fresh()->remaining, 0.001);

        $this->deleteJson("/admin-api/payoffs/{$payoff->id}")->assertNoContent();
        $this->assertEqualsWithDelta(1000, $invoice->fresh()->remaining, 0.001);
    }
}
