import { useNavigate, useSearchParams } from 'react-router';
import { Eye, Pencil, Plus } from 'lucide-react';
import { date, money, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, ButtonLink } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { useRecordActions } from '../../components/RecordActions';
import { EnumBadgeView } from './shared';
import { useSalesAbilities } from './useSalesAbilities';
import type { OrderRow } from './types';

/** The orders list (the panel's OrdersTable): newest first, searched by the customer, filtered by the customer and the payment type. */
export function OrdersPage() {
    const t = useT();
    const navigate = useNavigate();
    const abilities = useSalesAbilities();
    const [params, setParams] = useSearchParams();
    const customerId = params.get('customer_id');
    const actions = useRecordActions<OrderRow & { id: number }>('/orders', { label: (order) => `#${order.id}` });
    const field = (name: string) => t(`filament/resources/order.fields.${name}`);

    const setCustomer = (value: number | string | null) =>
        setParams((current) => {
            const next = new URLSearchParams(current);
            if (value) next.set('customer_id', String(value));
            else next.delete('customer_id');
            next.delete('page');
            return next;
        });

    return (
        <>
            <PageHeader title={t('filament/resources/order.plural_label')} />
            <DataTable<OrderRow>
                endpoint="/orders"
                trashed
                query={{ customer_id: customerId }}
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
                toolbar={(response) => (
                    <>
                        <div className="w-52">
                            <SearchSelect endpoint="/options/customers" value={customerId ? Number(customerId) : null} onChange={setCustomer} placeholder={field('customer_id')} />
                        </div>
                        {(response?.can as { create?: boolean } | undefined)?.create && (
                            <ButtonLink to="/orders/create" variant="primary" size="sm" icon={<Plus className="size-4" />}>
                                {t('admin.actions.new')}
                            </ButtonLink>
                        )}
                    </>
                )}
                rowHref={(order) => `/orders/${order.id}`}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (order) => <span className="text-muted tabular-nums">{order.id}</span> },
                    {
                        key: 'customer',
                        header: field('customer_id'),
                        cell: (order) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                {order.customer?.name ?? '—'}
                                {order.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'date', header: field('date'), sortable: true, cell: (order) => <span className="tabular-nums">{date(order.date)}</span> },
                    { key: 'payment_type', header: field('payment_type'), cell: (order) => <EnumBadgeView value={order.payment_type} /> },
                    { key: 'items_count', header: field('items_count'), align: 'end', cell: (order) => number(order.items_count) },
                    { key: 'total_amount', header: field('total_amount'), sortable: true, align: 'end', cell: (order) => <span className="font-medium tabular-nums">{money(order.total_amount)}</span> },
                    { key: 'down_payment', header: field('down_payment'), sortable: true, align: 'end', cell: (order) => (order.down_payment ? number(order.down_payment) : '-') },
                    { key: 'weekly_payment', header: field('weekly_payment'), sortable: true, align: 'end', cell: (order) => (order.weekly_payment ? number(order.weekly_payment) : '-') },
                    {
                        key: 'remaining',
                        header: field('remaining'),
                        sortable: true,
                        align: 'end',
                        cell: (order) => <span className={order.remaining > 0 ? 'text-danger tabular-nums' : 'tabular-nums'}>{number(order.remaining)}</span>,
                    },
                    { key: 'total_cost', header: field('total_cost'), sortable: true, align: 'end', cell: (order) => number(order.total_cost), hidden: !abilities?.see_cost },
                    { key: 'profit', header: field('profit'), sortable: true, align: 'end', cell: (order) => <span className="text-success tabular-nums">{number(order.profit)}</span>, hidden: !abilities?.see_cost },
                    { key: 'cashier', header: field('cashier_id'), cell: (order) => order.cashier ?? '—' },
                ]}
                rowActions={(order) => [
                    { label: t('admin.actions.view'), icon: <Eye className="size-4" />, onSelect: () => navigate(`/orders/${order.id}`) },
                    { label: t('admin.actions.edit'), icon: <Pencil className="size-4" />, onSelect: () => navigate(`/orders/${order.id}/edit`), hidden: !order.can.update },
                    ...actions.items(order).filter((item) => item.label !== t('admin.actions.edit')),
                ]}
            />
            {actions.dialogs}
        </>
    );
}
