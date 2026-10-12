<?php

namespace Tests\Feature;

use App\Invoices\PurchaseLabelDocument;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class DownloadPurchaseLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(PurchaseLabelDocument::class, function (MockInterface $mock): void {
            $mock->shouldReceive('pdf')->andReturn('%PDF-label');
        });
    }

    /**
     * EN: A user who can view the purchase invoice opens its carton label as a PDF in the browser (not a forced download),
     *     named label-PI-{id}.pdf when saved.
     * AR: اليوزر اللي يكدر يشوف فاتورة الشراء يفتح ملصق الكارتونة مالها كـ PDF بالمتصفح (مو تحميل إجباري)،
     *     واسمه label-PI-{id}.pdf لو انحفظ.
     */
    public function test_user_who_can_view_the_invoice_opens_the_label_pdf_in_the_browser(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $response = $this->actingAs($this->userWithPermissions(['invoice.view']))
            ->get(route('purchase-invoices.label', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', "inline; filename=label-PI-{$invoice->id}.pdf");
        $this->assertSame('%PDF-label', $response->getContent());
    }

    /**
     * EN: A user without the invoice view permission cannot download the label.
     * AR: اليوزر اللي ما عنده صلاحية عرض الفواتير ما يكدر ينزّل الملصق.
     */
    public function test_user_without_the_view_permission_cannot_download_the_label(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $this->actingAs($this->userWithPermissions([]))
            ->get(route('purchase-invoices.label', $invoice))
            ->assertForbidden();
    }

    /**
     * EN: A guest is sent to the login page instead of getting the label.
     * AR: الزائر اللي ما مسوي تسجيل دخول ينرسل لصفحة الدخول بدل ما ياخذ الملصق.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $this->get(route('purchase-invoices.label', $invoice))
            ->assertRedirect();
    }

    /**
     * EN: A general invoice (not a purchase) has no carton label.
     * AR: الفاتورة العامة (مو فاتورة شراء) ما عدها ملصق كارتونة.
     */
    public function test_general_invoice_has_no_label(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs(User::factory()->create(['is_super' => true]))
            ->get(route('purchase-invoices.label', $invoice))
            ->assertNotFound();
    }

    /**
     * EN: Creates a user and saves the given permissions for them.
     * AR: ينشئ يوزر ويحفظله الصلاحيات المحددة.
     *
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert([
                'user_id' => $user->id,
                'permission' => $permission,
            ]);
        }

        return $user;
    }
}
