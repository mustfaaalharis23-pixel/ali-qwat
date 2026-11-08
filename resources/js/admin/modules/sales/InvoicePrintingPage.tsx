import { Link, useSearchParams } from 'react-router';
import { date, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Input } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { EnumBadgeView, PrintMenu } from './shared';
import type { OrderRow } from './types';
import { useSalesAbilities } from './useSalesAbilities';

/**
 * The invoice printing page (the panel's InvoicePrinting page): every order, newest first, to look one up and print it as a full page or
 * as the small printer's receipt. The period, the customer, the cashier, and the payment type narrow it. The cost, the profit, and the
 * margin show only to a user who can see the cost.
 */
export function InvoicePrintingPage() {
    const t = useT();
    const abilities = useSalesAbilities();
    const [params, setParams] = useSearchParams();
    const field = (name: string) => t(`filament/resources/order.fields.${name}`);
    const value = (name: string) => params.get(name) ?? '';
    const set = (name: string, next: string | number | null) =>
        setParams(
            (current) => {
                const updated = new URLSearchParams(current);
                if (next === null || next === '') updated.delete(name);
                else updated.set(name, String(next));
                updated.delete('page');
                return updated;
            },
            { replace: true },
        );

    return (
        <>
            <PageHeader title={t('filament/pages/invoice_printing.title')} />
            <DataTable<OrderRow>
                endpoint="/invoice-printing"
                query={{ from: value('from'), until: value('until'), customer_id: value('customer_id'), cashier_id: value('cashier_id') }}
                defaultSort={{ column: 'date', direction: 'desc' }}
                filters={[
                    {
                        key: 'payment_type',
                        label: field('payment_type'),
                        options: [
                            { value: 'cash', label: t('orders.payment_types.cash') },
                            { value: 'postpaid', label: t('orders.payment_types.postpaid') },
                        ],
                    },
                ]}
                toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="w-48">
                            <SearchSelect endpoint="/options/customers" value={value('customer_id') ? Number(value('customer_id')) : null} onChange={(next) => set('customer_id', next)} placeholder={field('customer_id')} />
                        </div>
                        <div className="w-44">
                            <SearchSelect endpoint="/options/cashiers" value={value('cashier_id') ? Number(value('cashier_id')) : null} onChange={(next) => set('cashier_id', next)} placeholder={field('cashier_id')} />
                        </div>
                        <Input type="date" className="h-9 w-auto" value={value('from')} onChange={(event) => set('from', event.target.value)} aria-label={t('filament/pages/invoice_printing.filters.from')} title={t('filament/pages/invoice_printing.filters.from')} />
                        <Input type="date" className="h-9 w-auto" value={value('until')} onChange={(event) => set('until', event.target.value)} aria-label={t('filament/pages/invoice_printing.filters.until')} title={t('filament/pages/invoice_printing.filters.until')} />
                    </div>
                }
                columns={[
                    {
                        key: 'id',
                        header: t('filament/pages/invoice_printing.fields.number'),
                        sortable: true,
                        cell: (order) => (
                            <Link to={`/orders/${order.id}`} className="font-medium text-accent tabular-nums hover:underline">
                                #{order.id}
                            </Link>
                        ),
                    },
                    { key: 'date', header: field('date'), sortable: true, cell: (order) => <span className="tabular-nums">{date(order.date)}</span> },
                    { key: 'customer', header: field('customer_id'), sortable: true, cell: (order) => order.customer?.name ?? '—' },
                    { key: 'payment_type', header: field('payment_type'), cell: (order) => <EnumBadgeView value={order.payment_type} /> },
                    { key: 'items_count', header: field('items_count'), align: 'end', cell: (order) => number(order.items_count) },
                    { key: 'total_amount', header: field('total_amount'), sortable: true, align: 'end', cell: (order) => <span className="font-medium tabular-nums">{number(order.total_amount)}</span> },
                    { key: 'total_cost', header: field('total_cost'), sortable: true, align: 'end', hidden: !abilities?.see_cost, cell: (order) => number(order.total_cost) },
                    {
                        key: 'profit',
                        header: field('profit'),
                        sortable: true,
                        align: 'end',
                        hidden: !abilities?.see_cost,
                        cell: (order) => <span className={(order.profit ?? 0) < 0 ? 'text-danger tabular-nums' : 'text-success tabular-nums'}>{number(order.profit)}</span>,
                    },
                    {
                        key: 'profit_margin_percentage',
                        header: field('profit_margin_percentage'),
                        sortable: true,
                        align: 'end',
                        hidden: !abilities?.see_cost,
                        cell: (order) => <span className={(order.profit_margin_percentage ?? 0) < 0 ? 'text-danger tabular-nums' : 'text-success tabular-nums'}>{number(order.profit_margin_percentage, 1)}%</span>,
                    },
                    { key: 'cashier', header: field('cashier_id'), cell: (order) => order.cashier ?? '—' },
                    { key: 'print', header: '', align: 'end', cell: (order) => order.print && <PrintMenu links={order.print} size="sm" /> },
                ]}
            />
        </>
    );
}
