import { useState, type ReactNode } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Banknote, CalendarDays, ListOrdered, Pencil, Printer, RotateCcw, ShoppingBag, Trash2 } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, date, money, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, Card, CardBody, CardHeader, DescriptionList, PageSpinner } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { useRecordActions } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { PayDebtModal } from './PayDebtModal';
import { EnumBadgeView, PrintMenu } from './shared';
import type { OrderDetails } from './types';

function Amount({ label, value, tone }: { label: ReactNode; value: number; tone?: 'accent' | 'success' | 'danger' }) {
    return (
        <div className="rounded-xl border border-line p-4">
            <p className="text-xs font-medium text-muted">{label}</p>
            <p className={cn('mt-1.5 text-xl font-semibold tracking-tight tabular-nums', tone === 'accent' && 'text-accent', tone === 'success' && 'text-success', tone === 'danger' && 'text-danger')}>{money(value)}</p>
        </div>
    );
}

/**
 * An order's page (the panel's ViewOrder and OrderInfolist): its details, its amounts with what is owed written large, its weekly
 * payment, its lines, its payments, and its notes; with the print, pay, edit, and delete actions.
 */
export function OrderPage() {
    const t = useT();
    const { id } = useParams();
    const navigate = useNavigate();
    const [paying, setPaying] = useState(false);
    const field = (name: string) => t(`filament/resources/order.fields.${name}`);
    const { data, isLoading, error } = useQuery({ queryKey: ['record', 'orders', id], queryFn: () => api.get<{ data: OrderDetails }>(`/orders/${id}`) });
    const actions = useRecordActions<OrderDetails>('/orders', { label: (order) => `#${order.id}`, onDeleted: () => navigate('/orders') });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;

    const order = data.data;
    const seesCost = order.can.see_cost;

    return (
        <>
            <PageHeader
                breadcrumbs={[{ label: t('filament/resources/order.plural_label'), to: '/orders' }, { label: `#${order.id}` }]}
                title={`${t('filament/resources/order.label')} #${order.id}`}
                badge={order.deleted_at ? <Badge tone="danger">{t('admin.actions.deleted')}</Badge> : <EnumBadgeView value={order.payment_type} />}
                actions={
                    <>
                        {order.print && <PrintMenu links={order.print} />}
                        {order.can.pay && (
                            <Button variant="success" icon={<Banknote className="size-4" />} onClick={() => setPaying(true)}>
                                {t('filament/resources/customer.actions.pay_debt.label')}
                            </Button>
                        )}
                        {order.can.update && (
                            <ButtonLink to={`/orders/${order.id}/edit`} icon={<Pencil className="size-4" />}>
                                {t('admin.actions.edit')}
                            </ButtonLink>
                        )}
                        {order.can.restore && (
                            <Button icon={<RotateCcw className="size-4" />} onClick={() => actions.restore.mutate(order)}>
                                {t('admin.actions.restore')}
                            </Button>
                        )}
                        {order.can.delete && (
                            <Button variant="ghost" className="text-danger" icon={<Trash2 className="size-4" />} onClick={() => actions.items(order).find((item) => item.tone === 'danger' && !item.hidden)?.onSelect?.()}>
                                {t('admin.actions.delete')}
                            </Button>
                        )}
                    </>
                }
            />

            <div className="space-y-5">
                <Card>
                    <CardHeader icon={<ShoppingBag className="size-4.5" />} title={t('filament/resources/order.sections.details')} />
                    <CardBody>
                        <DescriptionList
                            columns={4}
                            items={[
                                {
                                    label: field('customer_id'),
                                    value: order.customer ? (
                                        <Link to={`/customers/${order.customer.id}`} className="font-medium hover:text-accent">
                                            {order.customer.name}
                                        </Link>
                                    ) : null,
                                },
                                { label: field('date'), value: <span className="tabular-nums">{date(order.date)}</span> },
                                { label: field('cashier_id'), value: order.cashier },
                                { label: field('user_id'), value: order.user },
                            ]}
                        />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader icon={<Banknote className="size-4.5" />} title={t('filament/resources/order.sections.amounts')} />
                    <CardBody className="space-y-5">
                        <div className={cn('grid grid-cols-1 gap-3 sm:grid-cols-2', seesCost ? 'xl:grid-cols-4' : 'xl:grid-cols-3')}>
                            <Amount label={field('total_amount')} value={order.total_amount} tone="accent" />
                            <Amount label={field('paid_amount')} value={order.paid_amount} tone="success" />
                            <Amount label={field('remaining')} value={order.remaining} tone={order.remaining > 0 ? 'danger' : 'success'} />
                            {seesCost && order.profit !== null && <Amount label={field('profit')} value={order.profit} tone="success" />}
                        </div>
                        <DescriptionList
                            columns={4}
                            items={[
                                { label: field('amount'), value: <span className="tabular-nums">{number(order.amount)}</span> },
                                { label: field('discount'), value: <span className="text-danger tabular-nums">{number(order.discount)}</span>, hidden: order.discount <= 0.0001 },
                                { label: field('down_payment'), value: order.down_payment ? number(order.down_payment) : '-' },
                                { label: field('weekly_payment'), value: order.weekly_payment ? number(order.weekly_payment) : '-' },
                                { label: field('total_cost'), value: number(order.total_cost), hidden: !seesCost },
                            ]}
                        />
                    </CardBody>
                </Card>

                {order.weekly && (
                    <Card>
                        <CardHeader icon={<CalendarDays className="size-4.5" />} title={field('weekly_payment')} />
                        <CardBody>
                            <DescriptionList
                                columns={4}
                                items={[
                                    { label: t('filament/resources/order.weekly.amount'), value: <span className="font-semibold tabular-nums">{number(order.weekly.amount)}</span> },
                                    { label: t('filament/pages/customer_payments.reminders.late_amount'), value: <Badge tone={order.weekly.is_late ? 'danger' : 'success'}>{order.weekly.late_text}</Badge> },
                                    { label: t('filament/resources/order.weekly.next'), value: <Badge tone={order.weekly.is_due_today ? 'warning' : 'gray'}>{order.weekly.next_text}</Badge> },
                                    { label: t('filament/resources/order.weekly.left'), value: order.weekly.left_text },
                                ]}
                            />
                        </CardBody>
                    </Card>
                )}

                <Card>
                    <CardHeader icon={<ListOrdered className="size-4.5" />} title={field('items')} />
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                                    <th className="px-4 py-2.5 text-start font-medium">{field('item')}</th>
                                    <th className="px-4 py-2.5 text-end font-medium">{field('quantity')}</th>
                                    <th className="px-4 py-2.5 text-end font-medium">{field('price')}</th>
                                    <th className="px-4 py-2.5 text-end font-medium">{field('total_price')}</th>
                                    {seesCost && <th className="px-4 py-2.5 text-end font-medium">{field('cost_price')}</th>}
                                    {seesCost && <th className="px-4 py-2.5 text-end font-medium">{field('profit')}</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {order.lines.map((line) => (
                                    <tr key={line.id} className="border-b border-line last:border-0">
                                        <td className="px-4 py-3">
                                            <p className="font-medium">{line.item?.name ?? '—'}</p>
                                            {(line.power !== null || line.colors.length > 0) && (
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    {line.power !== null && <Badge tone="info">{t('filament/resources/purchase_invoice.lines_below.power', { value: line.power })}</Badge>}
                                                    {line.colors.length > 0 && (
                                                        <Badge>
                                                            {line.colors.map((color) => t('filament/resources/invoice_item.group.color', { color: color.name, pieces: number(color.pieces) })).join(t('filament/resources/invoice_item.group.separator'))}
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-end tabular-nums">
                                            {number(line.quantity)}
                                            <p className="text-xs text-muted">{t('filament/resources/order.packets_and_pieces', { packets: number(line.packets), pieces: number(line.pieces) })}</p>
                                        </td>
                                        <td className="px-4 py-3 text-end tabular-nums">{number(line.price)}</td>
                                        <td className="px-4 py-3 text-end font-medium tabular-nums">{number(line.total_price)}</td>
                                        {seesCost && <td className="px-4 py-3 text-end tabular-nums">{number(line.cost_price)}</td>}
                                        {seesCost && <td className="px-4 py-3 text-end text-success tabular-nums">{number(line.profit)}</td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {order.payments && (
                    <Card>
                        <CardHeader icon={<Banknote className="size-4.5" />} title={t('filament/resources/order.sections.payments')} />
                        {order.payments.length === 0 ? (
                            <p className="px-5 py-6 text-center text-sm text-muted">{t('filament/resources/order.payments.none')}</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                                            <th className="px-4 py-2.5 text-start font-medium">{t('filament/resources/order.payments.date')}</th>
                                            <th className="px-4 py-2.5 text-end font-medium">{t('filament/resources/order.payments.amount')}</th>
                                            <th className="px-4 py-2.5 text-start font-medium">{t('filament/resources/order.payments.by')}</th>
                                            <th className="px-4 py-2.5 text-end font-medium">{t('filament/resources/order.payments.receipt')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {order.payments.map((payment) => (
                                            <tr key={payment.id} className="border-b border-line last:border-0">
                                                <td className="px-4 py-3 tabular-nums">
                                                    {date(payment.date)}
                                                    {payment.is_down_payment && <Badge tone="info" className="ms-2">{t('filament/resources/order.payments.down_payment')}</Badge>}
                                                </td>
                                                <td className="px-4 py-3 text-end font-semibold text-success tabular-nums">{number(payment.amount)}</td>
                                                <td className="px-4 py-3">{payment.user ?? '—'}</td>
                                                <td className="px-4 py-3 text-end">
                                                    <a href={payment.receipt_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-accent hover:underline">
                                                        <Printer className="size-3.5" />
                                                        {t('filament/resources/customer_payment.actions.print_receipt')}
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                <Card>
                    <CardBody>
                        <DescriptionList columns={1} items={[{ label: field('notes'), value: order.notes ? <span className="whitespace-pre-line">{order.notes}</span> : '—' }]} />
                    </CardBody>
                </Card>
            </div>

            {paying && <PayDebtModal orderId={order.id} customerId={order.customer?.id} onClose={() => setPaying(false)} />}
            {actions.dialogs}
        </>
    );
}
