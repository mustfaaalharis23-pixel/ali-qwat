<?php

namespace Tests\Unit;

use App\Permissions\PermissionName;
use Tests\TestCase;

class PermissionNameTest extends TestCase
{
    /**
     * EN: The name is the model name from the Policy, then a dot, then the ability: InvoicePolicy with view becomes invoice.view.
     * AR: الاسم يتكون من اسم الموديل من الـ Policy، بعدين نقطة، بعدين الـ ability: InvoicePolicy ويه view يصير invoice.view.
     */
    public function test_name_is_the_model_name_then_the_ability(): void
    {
        $this->assertSame('invoice.view', PermissionName::for('App\Policies\InvoicePolicy', 'view'));
    }

    /**
     * EN: A model name with more than one word becomes snake_case: SalesInvoicePolicy becomes sales_invoice.
     * AR: اسم الموديل اللي بيه أكثر من كلمة يصير snake_case: SalesInvoicePolicy يصير sales_invoice.
     */
    public function test_multi_word_model_name_becomes_snake_case(): void
    {
        $this->assertSame('sales_invoice.view', PermissionName::for('Tests\Fixtures\SalesInvoicePolicy', 'view'));
    }

    /**
     * EN: An ability with more than one word becomes snake_case: markAsPaid becomes mark_as_paid.
     * AR: الـ ability اللي بيها أكثر من كلمة تصير snake_case: markAsPaid تصير mark_as_paid.
     */
    public function test_multi_word_ability_becomes_snake_case(): void
    {
        $this->assertSame('sales_invoice.mark_as_paid', PermissionName::for('Tests\Fixtures\SalesInvoicePolicy', 'markAsPaid'));
    }
}
