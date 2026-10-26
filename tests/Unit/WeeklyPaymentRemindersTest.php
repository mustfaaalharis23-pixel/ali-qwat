<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use App\Orders\PayCustomerDebt;
use App\Orders\WeeklyPaymentReminders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WeeklyPaymentRemindersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A weekly payment falls due every 7 days from the order date: the customer owes it on day 7 and day 14, and nothing on day 9.
     *     An order without a weekly payment never shows.
     * AR: الدفعة الأسبوعية تستحق كل 7 أيام من تاريخ الأوردر: الزبون عليه دفعة باليوم 7 وباليوم 14، وباليوم 9 ما عليه شي.
     *     الأوردر اللي ما بيه دفعة أسبوعية ما يطلع أبداً.
     */
    public function test_weekly_payment_falls_due_every_seven_days_from_the_order_date(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100);
        $this->postpaidOrder(Customer::factory()->create(), '2026-10-01', total: 1000, weeklyPayment: null);

        $this->assertEquals([$customer->id => 100], $this->amounts($this->dueOn('2026-10-08')));
        $this->assertEquals([$customer->id => 100], $this->amounts($this->dueOn('2026-10-15')));
        $this->assertEquals([], $this->amounts($this->dueOn('2026-10-09')));
    }

    /**
     * EN: What the customer paid covers the weekly payments from the oldest: after paying 150, nothing is due on day 7,
     *     and only 50 is left of day 14's payment.
     * AR: اللي دفعه الزبون يغطي الدفعات الأسبوعية من الأقدم: بعد ما دفع 150، باليوم 7 ما عليه شي،
     *     ومن دفعة اليوم 14 باقي 50 بس.
     */
    public function test_what_the_customer_paid_covers_the_weekly_payments_from_the_oldest(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100);
        $this->pay($customer, 150, '2026-10-05');

        $this->assertEquals([], $this->amounts($this->dueOn('2026-10-08')));
        $this->assertEquals([$customer->id => 50], $this->amounts($this->dueOn('2026-10-15')));
    }

    /**
     * EN: The down payment is not a weekly payment: an order of 1000 with 300 down and 300 a week owes 300, 300, then the last 100,
     *     and nothing after what was left after the down payment is covered.
     * AR: الدفعة الأولية مو دفعة أسبوعية: أوردر بـ 1000 دفعته الأولية 300 وأسبوعياً 300 عليه 300، 300، وبعدين آخر 100،
     *     وما عليه شي بعد ما يتغطى الباقي بعد الدفعة الأولية.
     */
    public function test_weekly_payments_cover_what_is_left_after_the_down_payment_and_the_last_one_is_what_is_left(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 300, downPayment: 300);

        $this->assertEquals([$customer->id => 300], $this->amounts($this->dueOn('2026-10-08')));
        $this->assertEquals([$customer->id => 300], $this->amounts($this->dueOn('2026-10-15')));
        $this->assertEquals([$customer->id => 100], $this->amounts($this->dueOn('2026-10-22')));
        $this->assertEquals([], $this->amounts($this->dueOn('2026-10-29')));
    }

    /**
     * EN: A customer with two orders due on the same day shows once, with the two weekly payments added up.
     * AR: الزبون اللي عنده أوردرين يستحقن بنفس اليوم يطلع مرة وحدة، والدفعتين الأسبوعيتين مجموعات.
     */
    public function test_customer_with_two_orders_due_the_same_day_shows_once_with_the_sum(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100);
        $this->postpaidOrder($customer, '2026-10-08', total: 1000, weeklyPayment: 50);

        $this->assertEquals([$customer->id => 150], $this->amounts($this->dueOn('2026-10-15')));
    }

    /**
     * EN: Weekly payments whose day passed by one day or more and were not paid are late, counted from the oldest unpaid due date.
     *     The payment due today is not late yet.
     * AR: الدفعات الأسبوعية اللي فات يومها بيوم أو أكثر وما اندفعت متأخرة، من أقدم تاريخ استحقاق ما اندفع.
     *     دفعة اليوم بعدها مو متأخرة.
     */
    public function test_unpaid_weekly_payments_are_late_one_day_after_they_fall_due(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100);

        $lateOnTheSecondDueDay = $this->lateOn('2026-10-15');
        $this->assertEquals([$customer->id => 100], $this->amounts($lateOnTheSecondDueDay));
        $this->assertEquals([$customer->id => '2026-10-08'], $this->dueDates($lateOnTheSecondDueDay));

        $lateTheDayAfter = $this->lateOn('2026-10-16');
        $this->assertEquals([$customer->id => 200], $this->amounts($lateTheDayAfter));
        $this->assertEquals([$customer->id => '2026-10-08'], $this->dueDates($lateTheDayAfter));
    }

    /**
     * EN: What the customer paid covers the oldest late payments first: after paying 150 of three due payments of 100,
     *     150 is late, from the second due date.
     * AR: اللي دفعه الزبون يغطي أقدم الدفعات المتأخرة أول: بعد ما دفع 150 من ثلاث دفعات مستحقة كل وحدة 100،
     *     المتأخر 150، من تاريخ الاستحقاق الثاني.
     */
    public function test_what_the_customer_paid_covers_the_oldest_late_payments_first(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100);
        $this->pay($customer, 150, '2026-10-05');

        $late = $this->lateOn('2026-10-23');

        $this->assertEquals([$customer->id => 150], $this->amounts($late));
        $this->assertEquals([$customer->id => '2026-10-15'], $this->dueDates($late));
    }

    /**
     * EN: A customer who paid every payment that fell due is not late.
     * AR: الزبون اللي دفع كل دفعة استحقت عليه مو متأخر.
     */
    public function test_customer_who_paid_every_due_payment_is_not_late(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100);
        $this->pay($customer, 200, '2026-10-05');

        $this->assertEquals([], $this->amounts($this->lateOn('2026-10-16')));
    }

    /**
     * EN: Late payments never add up to more than the order: an order of 250 at 100 a week, long after its last due date, is 250 late.
     * AR: الدفعات المتأخرة ما تعبر الأوردر أبداً: أوردر بـ 250 وأسبوعياً 100، بعد آخر استحقاق بهواية، متأخر عليه 250.
     */
    public function test_late_payments_never_add_up_to_more_than_the_order(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 250, weeklyPayment: 100);

        $this->assertEquals([$customer->id => 250], $this->amounts($this->lateOn('2026-12-31')));
    }

    /**
     * EN: A customer late on two orders shows once: the late amounts added up, from the oldest unpaid due date of both.
     * AR: الزبون المتأخر على أوردرين يطلع مرة وحدة: المتأخر مجموع، من أقدم تاريخ استحقاق ما اندفع بالاثنين.
     */
    public function test_customer_late_on_two_orders_shows_once_from_the_oldest_due_date(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100);
        $this->postpaidOrder($customer, '2026-10-08', total: 1000, weeklyPayment: 50);

        $late = $this->lateOn('2026-10-16');

        $this->assertEquals([$customer->id => 250], $this->amounts($late));
        $this->assertEquals([$customer->id => '2026-10-08'], $this->dueDates($late));
    }

    /**
     * EN: The customers who owe a weekly payment on the date.
     * AR: الزباين اللي عليهم دفعة أسبوعية بالتاريخ.
     *
     * @return Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>
     */
    private function dueOn(string $date): Collection
    {
        return (new WeeklyPaymentReminders)->dueOn(CarbonImmutable::parse($date));
    }

    /**
     * EN: The customers who are late on weekly payments on the date.
     * AR: الزباين المتأخرين على دفعات أسبوعية بالتاريخ.
     *
     * @return Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>
     */
    private function lateOn(string $date): Collection
    {
        return (new WeeklyPaymentReminders)->lateOn(CarbonImmutable::parse($date));
    }

    /**
     * EN: Each customer's amount, keyed by the customer's id.
     * AR: مبلغ كل زبون، بمفتاح رقم الزبون.
     *
     * @param  Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>  $rows
     * @return array<int, float>
     */
    private function amounts(Collection $rows): array
    {
        return $rows->mapWithKeys(fn (array $row): array => [$row['customer']->id => $row['amount']])->all();
    }

    /**
     * EN: Each customer's oldest unpaid due date, keyed by the customer's id.
     * AR: أقدم تاريخ استحقاق ما اندفع لكل زبون، بمفتاح رقم الزبون.
     *
     * @param  Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>  $rows
     * @return array<int, string>
     */
    private function dueDates(Collection $rows): array
    {
        return $rows->mapWithKeys(fn (array $row): array => [$row['customer']->id => $row['due_date']->toDateString()])->all();
    }

    /**
     * EN: Sells the customer one line worth the total on the date, postpaid, with the weekly payment and the down payment given.
     * AR: يبيع للزبون بند واحد قيمته المجموع بالتاريخ، آجل، بالدفعة الأسبوعية والدفعة الأولية المحددة.
     */
    private function postpaidOrder(Customer $customer, string $date, float $total, ?float $weeklyPayment, ?float $downPayment = null): void
    {
        $item = Item::factory()->stocked()->create(['sale_price' => $total]);

        (new Checkout)->handle(User::factory()->create(), new CheckoutData(
            customerId: $customer->id,
            paymentType: OrderPaymentType::Postpaid,
            date: $date,
            lines: [new CheckoutLine($item->id, quantity: 1)],
            downPayment: $downPayment,
            weeklyPayment: $weeklyPayment,
        ));
    }

    /**
     * EN: Records a payment of the customer on the date, spread on their orders from the oldest.
     * AR: يسجّل تسديد للزبون بالتاريخ، يتوزع على أوردراته من الأقدم.
     */
    private function pay(Customer $customer, float $amount, string $date): void
    {
        (new PayCustomerDebt)->handle(User::factory()->create(['is_super' => true]), $customer, $amount, $date);
    }
}
