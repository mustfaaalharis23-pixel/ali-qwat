import { useState, type ReactNode } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Banknote, BellRing, CalendarCheck, Clock, Eye, FileText, Image as ImageIcon, Phone, Printer, ShoppingBag, UserCircle } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, date, money, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Button, Card, DescriptionList, Empty, Field, Input, Modal, Skeleton, Tabs } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { useRecordActions } from '../../components/RecordActions';
import { PayDebtModal } from './PayDebtModal';
import { statementPath } from './shared';
import type { CustomerPayment } from './types';

type Params = ReturnType<typeof useSearchParams>;

function useParam([params, setParams]: Params, name: string): [string, (value: string | number | null) => void] {
    return [
        params.get(name) ?? '',
        (value) =>
            setParams(
                (current) => {
                    const next = new URLSearchParams(current);
                    if (value === null || value === '') next.delete(name);
                    else next.set(name, String(value));
                    next.delete('page');
                    return next;
                },
                { replace: true },
            ),
    ];
}

/**
 * The customer payments page (the panel's CustomerPayments page): one tab per job: the payments, the account statement of a customer,
 * and the weekly payments due today and the late ones, each with how many customers it holds. A new payment is recorded from the header.
 * Opening the page with a customer (?customer=5) opens that customer's statement.
 */
export function CustomerPaymentsPage() {
    const t = useT();
    const search = useSearchParams();
    const [customer] = useParam(search, 'customer');
    const [tabParam, setTab] = useParam(search, 'tab');
    const tab = tabParam || (customer ? 'statement' : 'payments');
    const [paying, setPaying] = useState<{ customerId?: number } | null>(null);
    const page = 'filament/pages/customer_payments.';
    const { data: overview } = useQuery({
        queryKey: ['sales', 'customer-payments-overview'],
        queryFn: () => api.get<{ title: string; counts: { due_today: number; late: number }; can: { create: boolean } }>('/customer-payments/overview'),
    });

    return (
        <>
            <PageHeader
                title={t(`${page}title`)}
                actions={
                    overview?.can.create && (
                        <Button variant="success" icon={<Banknote className="size-4" />} onClick={() => setPaying({})}>
                            {t('filament/resources/customer.actions.pay_debt.label')}
                        </Button>
                    )
                }
            />
            <Tabs
                className="mb-5"
                value={tab}
                onChange={(value) => setTab(value)}
                tabs={[
                    { value: 'payments', label: <TabLabel icon={<Banknote className="size-3.5" />}>{t(`${page}tabs.payments`)}</TabLabel> },
                    { value: 'statement', label: <TabLabel icon={<FileText className="size-3.5" />}>{t(`${page}tabs.statement`)}</TabLabel> },
                    { value: 'due_today', label: <TabLabel icon={<BellRing className="size-3.5" />}>{t(`${page}tabs.due_today`)}</TabLabel>, count: overview?.counts.due_today || undefined },
                    { value: 'late', label: <TabLabel icon={<AlertTriangle className="size-3.5" />}>{t(`${page}tabs.late`)}</TabLabel>, count: overview?.counts.late || undefined },
                ]}
            />
            {tab === 'payments' && <PaymentsTab />}
            {tab === 'statement' && <StatementTab />}
            {(tab === 'due_today' || tab === 'late') && <RemindersTab key={tab} late={tab === 'late'} onPay={(customerId) => setPaying({ customerId })} />}
            {paying && <PayDebtModal customerId={paying.customerId} onClose={() => setPaying(null)} />}
        </>
    );
}

function TabLabel({ icon, children }: { icon: ReactNode; children: ReactNode }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            {icon}
            {children}
        </span>
    );
}

/** The payments tab: every payment, with its details in a window, its receipt to print, and delete. */
function PaymentsTab() {
    const t = useT();
    const navigate = useNavigate();
    const search = useSearchParams();
    const [customerId, setCustomerId] = useParam(search, 'p_customer');
    const [from, setFrom] = useParam(search, 'p_from');
    const [until, setUntil] = useParam(search, 'p_until');
    const [viewing, setViewing] = useState<number | null>(null);
    const actions = useRecordActions<CustomerPayment>('/customer-payments', { label: (payment) => `#${payment.id}` });
    const field = (name: string) => t(`filament/resources/customer_payment.fields.${name}`);

    return (
        <>
            <DataTable<CustomerPayment>
                endpoint="/customer-payments"
                trashed
                query={{ customer_id: customerId, from, until }}
                defaultSort={{ column: 'date', direction: 'desc' }}
                toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="w-52">
                            <SearchSelect endpoint="/options/customers" value={customerId ? Number(customerId) : null} onChange={(value) => setCustomerId(value)} placeholder={field('customer_id')} />
                        </div>
                        <Input type="date" className="h-9 w-auto" value={from} onChange={(event) => setFrom(event.target.value)} aria-label={t('filament/resources/customer_payment.filters.from')} title={t('filament/resources/customer_payment.filters.from')} />
                        <Input type="date" className="h-9 w-auto" value={until} onChange={(event) => setUntil(event.target.value)} aria-label={t('filament/resources/customer_payment.filters.until')} title={t('filament/resources/customer_payment.filters.until')} />
                    </div>
                }
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (payment) => <span className="text-muted tabular-nums">{payment.id}</span> },
                    {
                        key: 'customer',
                        header: field('customer_id'),
                        cell: (payment) =>
                            payment.customer ? (
                                <Link to={`/customers/${payment.customer.id}`} className="inline-flex items-center gap-1.5 font-semibold hover:text-accent">
                                    <UserCircle className="size-4 text-faint" />
                                    {payment.customer.name}
                                </Link>
                            ) : (
                                '—'
                            ),
                    },
                    {
                        key: 'date',
                        header: field('date'),
                        sortable: true,
                        cell: (payment) => (
                            <span className="inline-flex items-center gap-2 tabular-nums">
                                {date(payment.date)}
                                {payment.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    {
                        key: 'amount',
                        header: field('amount'),
                        sortable: true,
                        cell: (payment) => (
                            <div>
                                <Badge tone="success" icon={<Banknote className="size-3" />}>
                                    {number(payment.amount)}
                                </Badge>
                                {payment.is_down_payment && <p className="mt-0.5 text-xs text-muted">{field('is_down_payment')}</p>}
                            </div>
                        ),
                    },
                    {
                        key: 'image',
                        header: field('image'),
                        cell: (payment) =>
                            payment.image_url ? (
                                <a href={payment.image_url} target="_blank" rel="noreferrer">
                                    <img src={payment.image_url} alt="" className="size-9 rounded-md object-cover ring-1 ring-line" />
                                </a>
                            ) : (
                                <span className="text-faint">—</span>
                            ),
                    },
                    { key: 'user', header: field('user_id'), cell: (payment) => payment.user ?? '—' },
                    { key: 'notes', header: field('notes'), cell: (payment) => <span className="line-clamp-1 max-w-56 text-muted">{payment.notes ?? '—'}</span> },
                ]}
                rowActions={(payment) => [
                    { label: t('admin.actions.view'), icon: <Eye className="size-4" />, onSelect: () => setViewing(payment.id) },
                    { label: t('filament/resources/customer_payment.actions.print_receipt'), icon: <Printer className="size-4" />, href: payment.receipt_url ?? undefined, hidden: !payment.receipt_url },
                    { label: t('filament/resources/customer.actions.statement'), icon: <FileText className="size-4" />, onSelect: () => payment.customer && navigate(statementPath(payment.customer.id)) },
                    ...actions.items(payment),
                ]}
            />
            {viewing !== null && <PaymentDetails id={viewing} onClose={() => setViewing(null)} />}
            {actions.dialogs}
        </>
    );
}

/** A payment's details in a window (the panel's CustomerPaymentInfolist). */
function PaymentDetails({ id, onClose }: { id: number; onClose: () => void }) {
    const t = useT();
    const field = (name: string) => t(`filament/resources/customer_payment.fields.${name}`);
    const { data } = useQuery({ queryKey: ['record', 'customer-payments', id], queryFn: () => api.get<{ data: CustomerPayment }>(`/customer-payments/${id}`) });
    const payment = data?.data;

    return (
        <Modal open onClose={onClose} size="lg" title={t('filament/resources/customer_payment.sections.details')}>
            {!payment ? (
                <Skeleton className="h-40" />
            ) : (
                <div className="space-y-5">
                    <DescriptionList
                        columns={2}
                        items={[
                            { label: field('customer_id'), value: payment.customer ? <Link to={`/customers/${payment.customer.id}`} className="font-medium hover:text-accent" onClick={onClose}>{payment.customer.name}</Link> : '—' },
                            { label: field('amount'), value: <span className="font-semibold tabular-nums">{money(payment.amount)}</span> },
                            { label: field('date'), value: <span className="tabular-nums">{date(payment.date)}</span> },
                            { label: field('user_id'), value: payment.user ?? '—' },
                            { label: field('invoice_id'), value: payment.invoice ? <Link to={`/general-invoices/${payment.invoice.id}`} className="hover:text-accent" onClick={onClose}>{payment.invoice.name ?? `#${payment.invoice.id}`}</Link> : '—' },
                            { label: field('notes'), value: payment.notes ?? '—' },
                        ]}
                    />
                    <div>
                        <p className="mb-2 text-xs font-medium text-muted">{field('allocations')}</p>
                        <div className="overflow-x-auto rounded-lg border border-line">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                                        <th className="px-3 py-2 text-start font-medium">{field('order')}</th>
                                        <th className="px-3 py-2 text-start font-medium">{field('order_date')}</th>
                                        <th className="px-3 py-2 text-end font-medium">{field('order_total')}</th>
                                        <th className="px-3 py-2 text-end font-medium">{field('allocation_amount')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(payment.allocations ?? []).map((allocation) => (
                                        <tr key={allocation.order_id} className="border-b border-line last:border-0">
                                            <td className="px-3 py-2">
                                                <Link to={`/orders/${allocation.order_id}`} className="text-accent hover:underline" onClick={onClose}>
                                                    #{allocation.order_id}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">{date(allocation.order_date)}</td>
                                            <td className="px-3 py-2 text-end tabular-nums">{number(allocation.order_total)}</td>
                                            <td className="px-3 py-2 text-end font-medium tabular-nums">{number(allocation.amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    {payment.image_url && (
                        <div>
                            <p className="mb-2 text-xs font-medium text-muted">{t('filament/resources/customer_payment.sections.receipt')}</p>
                            <a href={payment.image_url} target="_blank" rel="noreferrer">
                                <img src={payment.image_url} alt="" className="max-h-80 rounded-lg ring-1 ring-line" />
                            </a>
                        </div>
                    )}
                    {payment.receipt_url && (
                        <a href={payment.receipt_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm text-accent hover:underline">
                            <Printer className="size-4" />
                            {t('filament/resources/customer_payment.actions.print_receipt')}
                        </a>
                    )}
                </div>
            )}
        </Modal>
    );
}

interface StatementRow {
    type: 'opening_balance' | 'order' | 'payment' | 'down_payment';
    date: string;
    movement: string;
    details: string;
    owed: number | null;
    paid: number | null;
    balance: number;
    order_id: number | null;
    image_url: string | null;
}

interface StatementOrder {
    id: number;
    date: string;
    items: number;
    total: number;
    paid: number;
    remaining: number;
    percent: number;
    weekly: number | null;
    late: number;
    late_days: number | null;
    next_text: string | null;
    payments: { date: string; amount: number; left_after: number; is_down_payment: boolean; user: string | null; notes: string | null; receipt_url: string }[];
}

interface Statement {
    customer: { id: number; name: string } | null;
    rows: StatementRow[];
    summary: { orders: number; payments: number; balance: number } | null;
    orders: StatementOrder[];
}

const movementTone = { opening_balance: 'gray', order: 'warning', payment: 'success', down_payment: 'info' } as const;

/**
 * The account statement tab: the customer and the period at the top, three cards that sum the period, then by order (each postpaid
 * order with the payments under it) or by date (one line per movement with the balance after it).
 */
function StatementTab() {
    const t = useT();
    const search = useSearchParams();
    const [customer, setCustomer] = useParam(search, 'customer');
    const [from, setFrom] = useParam(search, 'from');
    const [until, setUntil] = useParam(search, 'until');
    const [layoutParam, setLayout] = useParam(search, 'layout');
    const layout = layoutParam || 'orders';
    const key = 'filament/resources/customer.statement.';
    const { data, isLoading } = useQuery({
        queryKey: ['sales', 'statement', customer, from, until],
        queryFn: () => api.get<Statement>('/customer-payments/statement', { customer, from, until }),
        enabled: Boolean(customer),
    });

    return (
        <div className="space-y-5">
            <Card className="grid gap-4 p-4 md:grid-cols-3">
                <Field label={t(`${key}fields.customer`)}>
                    {(id) => <SearchSelect id={id} endpoint="/options/customers" value={customer ? Number(customer) : null} initialLabel={data?.customer?.name} onChange={(value) => setCustomer(value)} placeholder={t(`${key}fields.customer_placeholder`)} />}
                </Field>
                <Field label={t(`${key}fields.from`)}>{(id) => <Input id={id} type="date" value={from} onChange={(event) => setFrom(event.target.value)} />}</Field>
                <Field label={t(`${key}fields.until`)}>{(id) => <Input id={id} type="date" value={until} onChange={(event) => setUntil(event.target.value)} />}</Field>
            </Card>

            {!customer && <Card><Empty icon={<UserCircle className="size-5" />} title={t(`${key}choose_customer`)} /></Card>}
            {customer && isLoading && <Skeleton className="h-64" />}

            {customer && data?.summary && (
                <>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            { label: t(`${key}summary.orders`), value: data.summary.orders, tone: 'text-[oklch(0.5_0.12_60)]' },
                            { label: t(`${key}summary.payments`), value: data.summary.payments, tone: 'text-success' },
                            { label: t(`${key}summary.balance`), value: data.summary.balance, tone: data.summary.balance > 0.0001 ? 'text-danger' : 'text-success' },
                        ].map((card) => (
                            <div key={card.label} className="card p-4.5">
                                <p className="text-[13px] font-medium text-muted">{card.label}</p>
                                <p className={cn('mt-2 text-2xl font-semibold tracking-tight tabular-nums', card.tone)}>{money(card.value)}</p>
                            </div>
                        ))}
                    </div>

                    <Tabs
                        value={layout}
                        onChange={(value) => setLayout(value === 'orders' ? null : value)}
                        tabs={[
                            { value: 'orders', label: t(`${key}layouts.orders`) },
                            { value: 'dates', label: t(`${key}layouts.dates`) },
                        ]}
                    />

                    {layout === 'orders' ? <OrderTree orders={data.orders} /> : <StatementLines rows={data.rows} />}
                </>
            )}
        </div>
    );
}

function OrderTree({ orders }: { orders: StatementOrder[] }) {
    const t = useT();
    const key = 'filament/resources/customer.statement.tree.';
    if (orders.length === 0) return <Card><Empty icon={<FileText className="size-5" />} title={t(`${key}empty`)} /></Card>;

    return (
        <div className="space-y-3">
            {orders.map((order) => (
                <Card key={order.id}>
                    <div className="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4">
                        <div className="min-w-48 flex-1">
                            <Link to={`/orders/${order.id}`} className="inline-flex items-center gap-2 font-medium hover:text-accent">
                                <ShoppingBag className="size-4 text-faint" />
                                {t(`${key}order`, { id: order.id, date: date(order.date), items: order.items })}
                            </Link>
                            <div className="mt-2 h-1.5 w-full max-w-sm overflow-hidden rounded-full bg-subtle">
                                <div className={cn('h-full rounded-full', order.remaining > 0.0001 ? 'bg-accent' : 'bg-success')} style={{ width: `${order.percent}%` }} />
                            </div>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {order.remaining > 0.0001 ? <Badge tone="danger">{t(`${key}open`, { amount: number(order.remaining) })}</Badge> : <Badge tone="success">{t(`${key}settled`)}</Badge>}
                                {order.weekly !== null && <Badge>{t('filament/pages/customer_payments.order_reminders.weekly', { amount: number(order.weekly) })}</Badge>}
                                {order.late > 0.0001 && <Badge tone="danger">{t('filament/pages/customer_payments.order_reminders.late', { amount: number(order.late), days: order.late_days ?? 0 })}</Badge>}
                                {order.next_text && <Badge tone="warning">{order.next_text}</Badge>}
                            </div>
                        </div>
                        <dl className="grid grid-cols-3 gap-6 text-end text-sm">
                            <div>
                                <dt className="text-xs text-muted">{t(`${key}total`)}</dt>
                                <dd className="mt-0.5 font-medium tabular-nums">{number(order.total)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">{t(`${key}paid`)}</dt>
                                <dd className="mt-0.5 font-medium text-success tabular-nums">{number(order.paid)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">{t(`${key}remaining`)}</dt>
                                <dd className={cn('mt-0.5 font-semibold tabular-nums', order.remaining > 0.0001 ? 'text-danger' : 'text-success')}>{number(order.remaining)}</dd>
                            </div>
                        </dl>
                    </div>
                    <div className="border-t border-line bg-subtle/40 px-5 py-3">
                        {order.payments.length === 0 ? (
                            <p className="text-xs text-muted">{t(`${key}no_payments`)}</p>
                        ) : (
                            <ul className="space-y-2 border-s-2 border-line ps-4">
                                {order.payments.map((payment, index) => (
                                    <li key={index} className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                        <span className="text-muted tabular-nums">{date(payment.date)}</span>
                                        <span className="font-semibold text-success tabular-nums">{number(payment.amount)}</span>
                                        {payment.is_down_payment && <Badge tone="info">{t('filament/resources/customer.statement.types.down_payment')}</Badge>}
                                        <span className="text-xs text-muted">{t(`${key}left_after`, { amount: number(payment.left_after) })}</span>
                                        {payment.user && <span className="text-xs text-muted">{t(`${key}by`, { user: payment.user })}</span>}
                                        {payment.notes && <span className="text-xs text-muted">{payment.notes}</span>}
                                        <a href={payment.receipt_url} target="_blank" rel="noreferrer" className="ms-auto inline-flex items-center gap-1 text-xs text-accent hover:underline">
                                            <Printer className="size-3.5" />
                                            {t(`${key}receipt`)}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </Card>
            ))}
        </div>
    );
}

function StatementLines({ rows }: { rows: StatementRow[] }) {
    const t = useT();
    const key = 'filament/resources/customer.statement.';
    if (rows.length === 0) return <Card><Empty icon={<FileText className="size-5" />} title={t(`${key}empty`)} /></Card>;

    return (
        <Card className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                        <th className="px-4 py-2.5 text-start font-medium">{t(`${key}columns.date`)}</th>
                        <th className="px-4 py-2.5 text-start font-medium">{t(`${key}columns.movement`)}</th>
                        <th className="px-4 py-2.5 text-end font-medium">{t(`${key}columns.owed`)}</th>
                        <th className="px-4 py-2.5 text-end font-medium">{t(`${key}columns.paid`)}</th>
                        <th className="px-4 py-2.5 text-end font-medium">{t(`${key}columns.balance`)}</th>
                        <th className="w-24" />
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index} className="border-b border-line last:border-0 even:bg-subtle/30">
                            <td className="px-4 py-3 tabular-nums">{date(row.date)}</td>
                            <td className="px-4 py-3">
                                <Badge tone={movementTone[row.type]} icon={row.type === 'opening_balance' ? <Clock className="size-3" /> : row.type === 'order' ? <ShoppingBag className="size-3" /> : <Banknote className="size-3" />}>
                                    {row.movement}
                                </Badge>
                                <p className="mt-0.5 text-xs text-muted">{row.details}</p>
                            </td>
                            <td className="px-4 py-3 text-end text-danger tabular-nums">{row.owed !== null ? `+ ${number(row.owed)}` : ''}</td>
                            <td className="px-4 py-3 text-end text-success tabular-nums">{row.paid !== null ? `− ${number(row.paid)}` : ''}</td>
                            <td className={cn('px-4 py-3 text-end font-semibold tabular-nums', row.balance > 0.0001 ? 'text-danger' : 'text-success')}>{number(row.balance)}</td>
                            <td className="px-4 py-3 text-end">
                                {row.order_id && (
                                    <Link to={`/orders/${row.order_id}`} className="inline-flex items-center gap-1 text-xs text-accent hover:underline">
                                        <ShoppingBag className="size-3.5" />
                                        {t(`${key}actions.open_order`)}
                                    </Link>
                                )}
                                {row.image_url && (
                                    <a href={row.image_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-accent hover:underline">
                                        <ImageIcon className="size-3.5" />
                                        {t(`${key}actions.open_receipt`)}
                                    </a>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Card>
    );
}

interface ReminderRow {
    customer: { id: number; name: string; phone: string | null };
    amount: number;
    due_date: string;
    days_late: number | null;
}

/** A reminders tab: the customers due a weekly payment today, or late on one, each to call, pay for, or open on their statement. */
function RemindersTab({ late, onPay }: { late: boolean; onPay: (customerId: number) => void }) {
    const t = useT();
    const navigate = useNavigate();
    const key = 'filament/pages/customer_payments.reminders.';
    const { data, isLoading } = useQuery({
        queryKey: ['sales', 'reminders', late],
        queryFn: () => api.get<{ late: boolean; can_pay: boolean; rows: ReminderRow[] }>('/customer-payments/reminders', { late: late ? 1 : 0 }),
    });

    if (isLoading) return <Skeleton className="h-48" />;
    if (!data || data.rows.length === 0) return <Card><Empty icon={<CalendarCheck className="size-5" />} title={t(late ? `${key}empty_late` : `${key}empty_due`)} /></Card>;

    return (
        <Card>
            <ul className="divide-y divide-line">
                {data.rows.map((row) => (
                    <li key={row.customer.id} className="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-3.5">
                        <div className="min-w-48 flex-1">
                            <Link to={`/customers/${row.customer.id}`} className="inline-flex items-center gap-1.5 font-semibold hover:text-accent">
                                <UserCircle className="size-4 text-faint" />
                                {row.customer.name}
                            </Link>
                            {row.customer.phone && (
                                <a href={`tel:${row.customer.phone}`} dir="ltr" className="mt-0.5 flex items-center gap-1 text-xs text-muted hover:text-ink">
                                    <Phone className="size-3" />
                                    {row.customer.phone}
                                </a>
                            )}
                        </div>
                        <div className="text-sm">
                            <p className="text-xs text-muted">{t(late ? `${key}late_amount` : `${key}due_amount`)}</p>
                            <Badge tone={late ? 'danger' : 'warning'} icon={<Banknote className="size-3" />} className="mt-1">
                                {number(row.amount)}
                            </Badge>
                        </div>
                        {late && (
                            <div className="text-sm">
                                <p className="text-xs text-muted">{t(`${key}due_since`)}</p>
                                <p className="mt-1 tabular-nums">
                                    {date(row.due_date)} · <span className="font-bold text-danger">{t(`${key}days`, { days: row.days_late ?? 0 })}</span>
                                </p>
                            </div>
                        )}
                        <div className="flex gap-2">
                            {data.can_pay && (
                                <Button size="sm" variant="success" icon={<Banknote className="size-3.5" />} onClick={() => onPay(row.customer.id)}>
                                    {t('filament/resources/customer.actions.pay_debt.label')}
                                </Button>
                            )}
                            <Button size="sm" icon={<FileText className="size-3.5" />} onClick={() => navigate(statementPath(row.customer.id))}>
                                {t('filament/resources/customer.actions.statement')}
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>
        </Card>
    );
}
