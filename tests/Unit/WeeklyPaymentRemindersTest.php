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
     * EN: The reminder of one order, before anything falls due: an order of 1000 on 2026-10-01 with 200 down and 100 a week
     *     (8 weekly payments for the 800 left). On 2026-10-05 nothing is late, the next payment is 100 on 2026-10-08,
     *     800 is left on the order, and 8 weekly payments are left.
     * AR: تذكير أوردر واحد، قبل ما يستحق شي: أوردر بـ 1000 بتاريخ 2026-10-01 دفعته الأولية 200 والأسبوعية 100
     *     (8 دفعات أسبوعية للـ 800 الباقية). بتاريخ 2026-10-05 ما متأخر شي، والدفعة الجاية 100 بتاريخ 2026-10-08،
     *     والباقي على الأوردر 800، وباقي 8 دفعات أسبوعية.
     */
    public function test_an_order_reminder_before_anything_falls_due(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100, downPayment: 200);

        $this->assertEquals([
            'late' => 0,
            'late_since' => null,
            'next_due_date' => '2026-10-08',
            'next_amount' => 100,
            'remaining' => 800,
            'weeks_left' => 8,
        ], $this->orderReminder($customer, '2026-10-05'));
    }

    /**
     * EN: A late order: the same order, paid 100 on 2026-10-09. On 2026-10-23 three payments fell due (10-08, 10-15, 10-22)
     *     and one is paid, so 200 is late since 2026-10-15, the next payment is 100 on 2026-10-29, and 700 is left in 7 payments.
     * AR: أوردر متأخر: نفس الأوردر، واندفع منه 100 بتاريخ 2026-10-09. بتاريخ 2026-10-23 استحقت ثلاث دفعات (10-08، 10-15، 10-22)
     *     واندفعت وحدة، فمتأخر 200 من 2026-10-15، والدفعة الجاية 100 بتاريخ 2026-10-29، والباقي 700 بـ 7 دفعات.
     */
    public function test_a_late_order_shows_what_is_late_since_when_and_the_next_payment(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100, downPayment: 200);
        $this->pay($customer, 100, '2026-10-09');

        $this->assertEquals([
            'late' => 200,
            'late_since' => '2026-10-15',
            'next_due_date' => '2026-10-29',
            'next_amount' => 100,
            'remaining' => 700,
            'weeks_left' => 7,
        ], $this->orderReminder($customer, '2026-10-23'));
    }

    /**
     * EN: A customer who paid ahead: 400 paid on 2026-10-02 covers the first four payments, so on 2026-10-10 nothing is late
     *     and the next payment is the fifth, 100 on 2026-11-05. 400 is left in 4 payments.
     * AR: زبون دافع مقدماً: 400 اندفعت بتاريخ 2026-10-02 تغطي أول أربع دفعات، فبتاريخ 2026-10-10 ما متأخر شي
     *     والدفعة الجاية هي الخامسة، 100 بتاريخ 2026-11-05. الباقي 400 بـ 4 دفعات.
     */
    public function test_paying_ahead_moves_the_next_payment_forward(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100, downPayment: 200);
        $this->pay($customer, 400, '2026-10-02');

        $reminder = $this->orderReminder($customer, '2026-10-10');

        $this->assertEquals(0, $reminder['late']);
        $this->assertEquals('2026-11-05', $reminder['next_due_date']);
        $this->assertEquals(100, $reminder['next_amount']);
        $this->assertEquals(4, $reminder['weeks_left']);
    }

    /**
     * EN: Part of a payment already paid: 150 paid covers the payment of 2026-10-08 and half of the next, so the next payment
     *     on 2026-10-15 is only the 50 left of it.
     * AR: جزء من دفعة مدفوع: 150 اندفعت تغطي دفعة 2026-10-08 ونص اللي بعدها، فالدفعة الجاية
     *     بتاريخ 2026-10-15 هي بس الـ 50 الباقية منها.
     */
    public function test_the_next_payment_is_what_is_left_of_it(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100, downPayment: 200);
        $this->pay($customer, 150, '2026-10-02');

        $reminder = $this->orderReminder($customer, '2026-10-10');

        $this->assertEquals(0, $reminder['late']);
        $this->assertEquals('2026-10-15', $reminder['next_due_date']);
        $this->assertEquals(50, $reminder['next_amount']);
    }

    /**
     * EN: A payment that falls due today is the next payment, not a late one: on 2026-10-08, with nothing paid, nothing is late
     *     and the next payment is 100 today.
     * AR: الدفعة اللي تستحق اليوم هي الدفعة الجاية، مو متأخرة: بتاريخ 2026-10-08، وما مدفوع شي، ما متأخر شي
     *     والدفعة الجاية 100 اليوم.
     */
    public function test_a_payment_due_today_is_the_next_one_and_not_late(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 1000, weeklyPayment: 100, downPayment: 200);

        $reminder = $this->orderReminder($customer, '2026-10-08');

        $this->assertEquals(0, $reminder['late']);
        $this->assertEquals('2026-10-08', $reminder['next_due_date']);
        $this->assertEquals(100, $reminder['next_amount']);
    }

    /**
     * EN: The last payment is only what is left: an order of 950 with 200 down and 100 a week leaves 750, so seven payments of 100
     *     and a last one of 50. With 700 paid, on 2026-11-20 the next payment is the last, 50 on 2026-11-26, one payment left.
     * AR: آخر دفعة هي بس الباقي: أوردر بـ 950 دفعته الأولية 200 والأسبوعية 100 يبقى منه 750، يعني سبع دفعات كل وحدة 100
     *     وآخر وحدة 50. ومدفوع 700، فبتاريخ 2026-11-20 الدفعة الجاية هي الأخيرة، 50 بتاريخ 2026-11-26، وباقي دفعة وحدة.
     */
    public function test_the_last_payment_of_an_order_is_what_is_left(): void
    {
        $customer = Customer::factory()->create();
        $this->postpaidOrder($customer, '2026-10-01', total: 950, weeklyPayment: 100, downPayment: 200);
        $this->pay($customer, 700, '2026-11-01');

        $reminder = $this->orderReminder($customer, '2026-11-20');

        $this->assertEquals(0, $reminder['late']);
        $this->assertEquals('2026-11-26', $reminder['next_due_date']);
        $this->assertEquals(50, $reminder['next_amount']);
        $this->assertEquals(1, $reminder['weeks_left']);
    }

    /**
     * EN: Every postpaid order with a weekly payment has its own reminder until it is fully paid: a customer with two such orders
     *     has two reminders. A fully paid order and an order without a weekly payment have none.
     * AR: كل أوردر آجل بي دفعة أسبوعية إله تذكيره لحد ما يتسدد كله: زبون عنده أوردرين من هذا النوع
     *     إله تذكيرين. والأوردر المسدد كله والأوردر بلا دفعة أسبوعية ما إلهم تذكير.
     */
    public function test_every_open_order_with_a_weekly_payment_has_a_reminder_until_it_is_paid(): void
    {
        $twoOrders = Customer::factory()->create();
        $this->postpaidOrder($twoOrders, '2026-10-01', total: 1000, weeklyPayment: 100);
        $this->postpaidOrder($twoOrders, '2026-10-03', total: 500, weeklyPayment: 50);
        $paidOff = Customer::factory()->create();
        $this->postpaidOrder($paidOff, '2026-10-01', total: 300, weeklyPayment: 100);
        $this->pay($paidOff, 300, '2026-10-02');
        $this->postpaidOrder(Customer::factory()->create(), '2026-10-01', total: 1000, weeklyPayment: null);

        $reminders = (new WeeklyPaymentReminders)->ordersOn(CarbonImmutable::parse('2026-10-05'));

        $this->assertEquals([$twoOrders->id, $twoOrders->id], $reminders->map(fn (array $row): int => $row['order']->customer_id)->all());
    }

    /**
     * EN: The late reminders come first, the oldest late first, then the others by their next payment, the nearest first.
     * AR: تذكيرات المتأخرين تطلع أول، الأقدم تأخيراً أول، وبعدين الباقي حسب دفعتهم الجاية، الأقرب أول.
     */
    public function test_late_reminders_come_first_then_the_nearest_next_payment(): void
    {
        $lateLong = Customer::factory()->create();
        $this->postpaidOrder($lateLong, '2026-09-01', total: 1000, weeklyPayment: 100);
        $lateShort = Customer::factory()->create();
        $this->postpaidOrder($lateShort, '2026-10-01', total: 1000, weeklyPayment: 100);
        $dueLater = Customer::factory()->create();
        $this->postpaidOrder($dueLater, '2026-10-12', total: 1000, weeklyPayment: 100);
        $dueSooner = Customer::factory()->create();
        $this->postpaidOrder($dueSooner, '2026-10-10', total: 1000, weeklyPayment: 100);

        $reminders = (new WeeklyPaymentReminders)->ordersOn(CarbonImmutable::parse('2026-10-12'));

        $this->assertEquals(
            [$lateLong->id, $lateShort->id, $dueSooner->id, $dueLater->id],
            $reminders->map(fn (array $row): int => $row['order']->customer_id)->all(),
        );
    }

    /**
     * EN: The reminder of the customer's only order on the date, with its dates as text.
     * AR: تذكير الأوردر الوحيد للزبون بالتاريخ، وتواريخه كنص.
     *
     * @return array{late: float, late_since: ?string, next_due_date: ?string, next_amount: float, remaining: float, weeks_left: int}
     */
    private function orderReminder(Customer $customer, string $date): array
    {
        $row = (new WeeklyPaymentReminders)->ordersOn(CarbonImmutable::parse($date))
            ->sole(fn (array $row): bool => $row['order']->customer_id === $customer->id);

        return [
            'late' => $row['late'],
            'late_since' => $row['late_since']?->toDateString(),
            'next_due_date' => $row['next_due_date']?->toDateString(),
            'next_amount' => $row['next_amount'],
            'remaining' => $row['remaining'],
            'weeks_left' => $row['weeks_left'],
        ];
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
