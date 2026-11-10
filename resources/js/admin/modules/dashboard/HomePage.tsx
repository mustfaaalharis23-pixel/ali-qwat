import { useMemo, useState } from 'react';
import { Link } from 'react-router';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AlarmClock, ArrowUpRight, Banknote, BellRing, CalendarCheck, ChartLine, CircleCheck, CircleUser, FileText, Phone, ShoppingBag, ShoppingCart, TriangleAlert } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, date, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { useSession } from '../../lib/session';
import { useNavigation } from '../../layout/AppLayout';
import { Badge, Button, ButtonLink, Card, CardHeader, Empty, Skeleton, Tabs } from '../../components/ui';
import { RemoteMetric } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { PayDebtModal } from '../sales/PayDebtModal';
import { statementPath } from '../sales/shared';

interface Reminder {
    order_id: number;
    customer_id: number;
    customer: string | null;
    phone: string | null;
    weekly_payment: number;
    late: number;
    late_since: string | null;
    days_late: number | null;
    next_due_date: string | null;
    next_amount: number;
    remaining: number;
    weeks_left: number;
    is_due_today: boolean;
}

interface CustomerReminders {
    customer_id: number;
    customer: string | null;
    phone: string | null;
    orders: Reminder[];
}

const isLate = (row: Reminder) => row.late > 0.0001;

/** The reminders grouped by customer, each customer where their first order shows (the late first). */
function byCustomer(rows: Reminder[]): CustomerReminders[] {
    const customers = new Map<number, CustomerReminders>();
    rows.forEach((row) => {
        const customer = customers.get(row.customer_id) ?? { customer_id: row.customer_id, customer: row.customer, phone: row.phone, orders: [] };
        customer.orders.push(row);
        customers.set(row.customer_id, customer);
    });
    return [...customers.values()];
}

export function HomePage() {
    const t = useT();
    const client = useQueryClient();
    const { data: session } = useSession();
    const { data: navigation } = useNavigation();
    const [tab, setTab] = useState('today');
    const [paying, setPaying] = useState<{ customerId: number; orderId?: number } | null>(null);
    const { data, isLoading } = useQuery({ queryKey: ['dashboard', 'reminders'], queryFn: () => api.get<{ visible: boolean; rows: Reminder[]; can_pay: boolean }>('/dashboard/reminders') });
    const reminder = (key: string, replace?: Record<string, string | number>) => t(`filament/pages/customer_payments.order_reminders.${key}`, replace);
    const payLabel = t('filament/resources/customer.actions.pay_debt.label');

    const customers = useMemo(() => {
        const all = data?.rows ?? [];
        return byCustomer(tab === 'today' ? all.filter((row) => row.is_due_today) : tab === 'late' ? all.filter(isLate) : all);
    }, [data, tab]);

    const paths = new Set((navigation?.groups ?? []).flatMap((group) => group.items.map((item) => item.path)));
    const actions = [
        { path: '/orders/create', when: '/orders', label: t('admin.home.action_new_order'), hint: t('admin.home.action_new_order_hint'), icon: <ShoppingBag className="size-4.5" /> },
        { path: '/purchase-invoices/create', when: '/purchase-invoices', label: t('admin.home.action_new_purchase'), hint: t('admin.home.action_new_purchase_hint'), icon: <ShoppingCart className="size-4.5" /> },
        { path: '/customer-payments', when: '/customer-payments', label: t('admin.home.action_payment'), hint: t('admin.home.action_payment_hint'), icon: <Banknote className="size-4.5" /> },
        { path: '/financial-dashboard', when: '/financial-dashboard', label: t('filament/pages/financial_dashboard.title'), hint: t('admin.home.action_financial_hint'), icon: <ChartLine className="size-4.5" /> },
    ].filter((action) => paths.has(action.when));
    const canSeeMoney = paths.has('/financial-dashboard');
    const today = new Intl.DateTimeFormat(document.documentElement.lang || 'ar', { weekday: 'long', day: 'numeric', month: 'long', numberingSystem: 'latn' }).format(new Date());

    return (
        <>
            <PageHeader title={t('admin.home.greeting', { name: session?.user?.name ?? '' })} description={<><span className="font-medium text-ink">{today}</span> · {t('admin.home.subtitle')}</>} />

            {actions.length > 0 && (
                <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {actions.map((action) => (
                        <Link key={action.path} to={action.path} className="card group flex items-center gap-3.5 p-4 transition-[border-color,box-shadow] hover:border-accent/30 hover:shadow-[var(--shadow-raised)]">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent-soft text-accent ring-1 ring-accent/10">{action.icon}</span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-[14px] font-semibold">{action.label}</span>
                                <span className="block truncate text-[12.5px] text-muted">{action.hint}</span>
                            </span>
                            <ArrowUpRight className="size-4 shrink-0 text-faint transition-colors group-hover:text-accent rtl:-scale-x-100" />
                        </Link>
                    ))}
                </div>
            )}

            {canSeeMoney && (
                <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {['accountant_balance', 'cash_sales_box', 'postpaid_sales_box', 'total_debt'].map((key) => (
                        <RemoteMetric key={key} endpoint={`/dashboard/metrics/${key}`} />
                    ))}
                </div>
            )}

            {data?.visible !== false && (
                <Card>
                    <CardHeader
                        icon={<AlarmClock className="size-4.5" />}
                        title={t('filament/pages/customer_payments.order_reminders.heading')}
                        description={t('filament/pages/customer_payments.order_reminders.description')}
                        actions={
                            <Tabs
                                value={tab}
                                onChange={setTab}
                                tabs={[
                                    { value: 'today', label: reminder('tab_today'), count: data?.rows.filter((row) => row.is_due_today).length },
                                    { value: 'late', label: reminder('tab_late'), count: data?.rows.filter(isLate).length },
                                    { value: 'all', label: reminder('tab_all'), count: data?.rows.length },
                                ]}
                            />
                        }
                    />
                    {isLoading && (
                        <div className="space-y-3 p-5">
                            {[1, 2, 3].map((index) => (
                                <Skeleton key={index} className="h-12" />
                            ))}
                        </div>
                    )}
                    {!isLoading && customers.length === 0 && (
                        <Empty
                            icon={<CalendarCheck className="size-5" />}
                            title={
                                tab === 'today'
                                    ? t('filament/pages/customer_payments.order_reminders.empty_today')
                                    : tab === 'late'
                                      ? t('filament/pages/customer_payments.order_reminders.empty_late')
                                      : t('filament/pages/customer_payments.order_reminders.empty')
                            }
                        />
                    )}
                    {customers.length > 0 && (
                        <ul className="divide-y divide-line">
                            {customers.map((customer) => {
                                const late = customer.orders.reduce((sum, row) => sum + row.late, 0);
                                const dueToday = customer.orders.filter((row) => row.is_due_today).reduce((sum, row) => sum + row.next_amount, 0);
                                const daysLate = Math.max(0, ...customer.orders.map((row) => row.days_late ?? 0));
                                const remaining = customer.orders.reduce((sum, row) => sum + row.remaining, 0);
                                return (
                                    <li key={customer.customer_id} className="space-y-3 px-5 py-4">
                                        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                            <div className="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1">
                                                <Link to={`/customers/${customer.customer_id}`} className="inline-flex items-center gap-1.5 font-semibold hover:text-accent">
                                                    <CircleUser className="size-4.5 text-faint" />
                                                    {customer.customer}
                                                </Link>
                                                {customer.phone && (
                                                    <a href={`tel:${customer.phone}`} className="inline-flex items-center gap-1 text-[13px] text-muted tabular-nums hover:text-ink" dir="ltr">
                                                        <Phone className="size-3.5" />
                                                        {customer.phone}
                                                    </a>
                                                )}
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {tab === 'today' && dueToday > 0 && <Badge tone="warning" icon={<BellRing />}>{reminder('due_today_value', { amount: number(dueToday) })}</Badge>}
                                                {late > 0.0001 ? (
                                                    <Badge tone="danger" icon={<TriangleAlert />}>{reminder('late', { amount: number(late), days: daysLate })}</Badge>
                                                ) : (
                                                    <Badge tone="success" icon={<CircleCheck />}>{reminder('not_late')}</Badge>
                                                )}
                                                <Badge>{reminder('remaining_value', { amount: number(remaining) })}</Badge>
                                                {data?.can_pay && (
                                                    <Button size="sm" variant="success" icon={<Banknote className="size-4" />} onClick={() => setPaying({ customerId: customer.customer_id })}>
                                                        {payLabel}
                                                    </Button>
                                                )}
                                                <ButtonLink size="sm" to={statementPath(customer.customer_id)} icon={<FileText className="size-4" />}>
                                                    {t('filament/resources/customer.actions.statement')}
                                                </ButtonLink>
                                            </div>
                                        </div>
                                        <div className="grid gap-1.5">
                                            {customer.orders.map((row) => (
                                                <div
                                                    key={row.order_id}
                                                    className={cn('flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-lg border-s-[3px] bg-subtle px-3 py-2 text-[13px]', isLate(row) ? 'border-danger' : 'border-transparent')}
                                                >
                                                    <Link to={`/orders/${row.order_id}`} className="font-semibold text-accent hover:underline">
                                                        {reminder('order', { number: row.order_id })}
                                                    </Link>
                                                    <span className="text-muted">{reminder('weekly', { amount: number(row.weekly_payment) })}</span>
                                                    {isLate(row) ? (
                                                        <Badge tone="danger">{reminder('late', { amount: number(row.late), days: row.days_late ?? 0 })}</Badge>
                                                    ) : (
                                                        <Badge tone="success">{reminder('not_late')}</Badge>
                                                    )}
                                                    {row.next_due_date ? (
                                                        <Badge tone={row.is_due_today ? 'warning' : 'gray'}>
                                                            {row.is_due_today
                                                                ? reminder('next_today', { amount: number(row.next_amount) })
                                                                : reminder('next', { amount: number(row.next_amount), date: date(row.next_due_date) })}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted">{reminder('no_next')}</span>
                                                    )}
                                                    <span className="ms-auto font-medium tabular-nums">{reminder('left', { amount: number(row.remaining), weeks: row.weeks_left })}</span>
                                                    {data?.can_pay && (
                                                        <button
                                                            type="button"
                                                            className="inline-flex items-center gap-1 font-medium text-success hover:underline"
                                                            onClick={() => setPaying({ customerId: row.customer_id, orderId: row.order_id })}
                                                        >
                                                            <Banknote className="size-3.5" />
                                                            {payLabel}
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Card>
            )}

            {paying && (
                <PayDebtModal
                    customerId={paying.customerId}
                    orderId={paying.orderId}
                    onClose={() => {
                        setPaying(null);
                        client.invalidateQueries({ queryKey: ['dashboard'] });
                    }}
                />
            )}
        </>
    );
}
