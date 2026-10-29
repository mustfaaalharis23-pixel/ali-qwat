<?php

namespace App\Orders;

use App\Models\Customer;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The weekly payments the customers are reminded of. A postpaid order with a weekly payment owes it every 7 days
 * from its date, until what was left after its down payment is covered, so the last one is only what is left.
 * What the customer paid on the order, apart from the down payment, covers its weekly payments from the oldest.
 * A weekly payment is late from the day after it falls due. Today's and the late reminders show each customer once, with their
 * orders added up; the reminders of the orders show every order on its own until it is fully paid.
 */
class WeeklyPaymentReminders
{
    /**
     * The customers who owe a weekly payment on the date: what is still unpaid of the weekly payments that fall due that day.
     *
     * @return Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>
     */
    public function dueOn(CarbonInterface $date): Collection
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return $this->perCustomer($this->openOrders()->map(fn (Order $order): array => [
            'order' => $order,
            'amount' => $this->expectedBy($order, $day) - max($this->paidOnWeeklyPayments($order), $this->expectedBy($order, $day->subDay())),
            'due_date' => $day,
        ]))->sortBy(fn (array $row): string => $row['customer']->name)->values();
    }

    /**
     * The customers who are late on the date: what is unpaid of the weekly payments that fell due before it,
     * from the oldest of them that is not fully paid. The oldest late customers come first.
     *
     * @return Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>
     */
    public function lateOn(CarbonInterface $date): Collection
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return $this->perCustomer($this->openOrders()->map(fn (Order $order): array => [
            'order' => $order,
            'amount' => $this->expectedBy($order, $day->subDay()) - $this->paidOnWeeklyPayments($order),
            'due_date' => $this->firstUnpaidDueDate($order),
        ]))->sortBy(fn (array $row): string => $row['due_date']->toDateString())->values();
    }

    /**
     * The reminder of every order with a weekly payment that is not fully paid yet, on the date: what is late and since when, the
     * next weekly payment (its due date and what is unpaid of it, the one due on the date included), what is left on the order,
     * and how many weekly payments that is. The late orders come first, the oldest late first, then the others by their next
     * weekly payment, the nearest first.
     *
     * @return Collection<int, array{order: Order, late: float, late_since: ?CarbonImmutable, next_due_date: ?CarbonImmutable, next_amount: float, remaining: float, weeks_left: int}>
     */
    public function ordersOn(CarbonInterface $date): Collection
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return $this->openOrders()
            ->map(function (Order $order) use ($day): array {
                $paid = $this->paidOnWeeklyPayments($order);
                $late = max(0, $this->expectedBy($order, $day->subDay()) - $paid);
                $nextDueDate = $this->nextDueDate($order, $day);

                return [
                    'order' => $order,
                    'late' => $late,
                    'late_since' => $late > 0.0001 ? $this->firstUnpaidDueDate($order) : null,
                    'next_due_date' => $nextDueDate,
                    'next_amount' => $nextDueDate === null
                        ? 0.0
                        : $this->expectedBy($order, $nextDueDate) - max($paid, $this->expectedBy($order, $nextDueDate->subDay())),
                    'remaining' => (float) $order->remaining,
                    'weeks_left' => (int) ceil(round((float) $order->remaining / (float) $order->weekly_payment, 4)),
                ];
            })
            ->sortBy(fn (array $row): string => implode('|', [
                $row['late_since'] === null ? '1' : '0',
                ($row['late_since'] ?? $row['next_due_date'])?->toDateString() ?? '9999-12-31',
                str_pad((string) $row['order']->id, 12, '0', STR_PAD_LEFT),
            ]))
            ->values();
    }

    /**
     * The due date of the first weekly payment on or after the date that is not fully paid, or null when the weekly payments
     * left all fell due before the date (the order is then only late).
     */
    private function nextDueDate(Order $order, CarbonImmutable $day): ?CarbonImmutable
    {
        $orderDate = $order->date->toImmutable()->startOfDay();
        $weeklyPayment = (float) $order->weekly_payment;
        $firstOnOrAfterTheDay = max(1, (int) ceil($orderDate->diffInDays($day) / 7));
        $paidWeeks = (int) floor(($this->paidOnWeeklyPayments($order) + 0.0001) / $weeklyPayment);
        $week = max($firstOnOrAfterTheDay, $paidWeeks + 1);

        if (($week - 1) * $weeklyPayment >= $this->owedInWeeklyPayments($order) - 0.0001) {
            return null;
        }

        return $orderDate->addDays(7 * $week);
    }

    /**
     * Adds up the orders' amounts per customer, keeping the oldest due date. Orders with nothing to pay are left out.
     *
     * @param  Collection<int, array{order: Order, amount: float, due_date: CarbonImmutable}>  $lines
     * @return Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>
     */
    private function perCustomer(Collection $lines): Collection
    {
        return $lines
            ->filter(fn (array $line): bool => $line['amount'] > 0.0001 && $line['order']->customer !== null)
            ->groupBy(fn (array $line): int => $line['order']->customer_id)
            ->map(fn (Collection $lines): array => [
                'customer' => $lines->first()['order']->customer,
                'amount' => (float) $lines->sum('amount'),
                'due_date' => $lines->min('due_date'),
            ])
            ->values();
    }

    /**
     * What the order's weekly payments come to by the end of the date: one weekly payment for every whole week since
     * the order's date, but never more than what was left after the down payment.
     */
    private function expectedBy(Order $order, CarbonImmutable $date): float
    {
        $orderDate = $order->date->toImmutable()->startOfDay();

        if ($date->lt($orderDate)) {
            return 0;
        }

        $weeks = intdiv((int) $orderDate->diffInDays($date), 7);

        return min($weeks * (float) $order->weekly_payment, $this->owedInWeeklyPayments($order));
    }

    /**
     * The due date of the oldest weekly payment of the order that is not fully paid.
     */
    private function firstUnpaidDueDate(Order $order): CarbonImmutable
    {
        $paidWeeks = (int) floor(($this->paidOnWeeklyPayments($order) + 0.0001) / (float) $order->weekly_payment);

        return $order->date->toImmutable()->startOfDay()->addDays(7 * ($paidWeeks + 1));
    }

    /**
     * What the order is paid by in weekly payments: what was left after its down payment.
     */
    private function owedInWeeklyPayments(Order $order): float
    {
        return (float) $order->total_amount - (float) ($order->down_payment ?? 0);
    }

    /**
     * What the customer paid on the order apart from its down payment.
     */
    private function paidOnWeeklyPayments(Order $order): float
    {
        return max((float) $order->paid_amount - (float) ($order->down_payment ?? 0), 0);
    }

    /**
     * The debts with a weekly payment, with their customers.
     *
     * @return Collection<int, Order>
     */
    private function openOrders(): Collection
    {
        return Order::query()
            ->debts()
            ->whereNotNull('weekly_payment')
            ->with('customer')
            ->get();
    }
}
