import { useMemo, useState } from 'react';
import { Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { AlarmClock, ArrowUpRight, Banknote, CalendarCheck, ChartLine, Phone, ShoppingBag, ShoppingCart } from 'lucide-react';
import { api } from '../../lib/api';
import { date, money } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { useSession } from '../../lib/session';
import { useNavigation } from '../../layout/AppLayout';
import { Badge, Card, CardHeader, Empty, Skeleton, Tabs } from '../../components/ui';
import { RemoteMetric } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';

interface Reminder {
    order_id: number;
    customer_id: number;
    customer: string | null;
    phone: string | null;
    late: number;
    late_since: string | null;
    next_due_date: string | null;
    next_amount: number;
    remaining: number;
    weeks_left: number;
    is_due_today: boolean;
}

export function HomePage() {
    const t = useT();
    const { data: session } = useSession();
    const { data: navigation } = useNavigation();
    const [tab, setTab] = useState('all');
    const { data, isLoading } = useQuery({ queryKey: ['dashboard', 'reminders'], queryFn: () => api.get<{ visible: boolean; rows: Reminder[] }>('/dashboard/reminders') });

    const rows = useMemo(() => {
        const all = data?.rows ?? [];
        if (tab === 'today') return all.filter((row) => row.is_due_today);
        if (tab === 'late') return all.filter((row) => row.late > 0);
        return all;
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
                                    { value: 'all', label: t('admin.home.reminders_all'), count: data?.rows.length },
                                    { value: 'today', label: t('admin.home.reminders_today'), count: data?.rows.filter((row) => row.is_due_today).length },
                                    { value: 'late', label: t('admin.home.reminders_late'), count: data?.rows.filter((row) => row.late > 0).length },
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
                    {!isLoading && rows.length === 0 && (
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
                    {rows.length > 0 && (
                        <ul className="divide-y divide-line">
                            {rows.map((row) => (
                                <li key={row.order_id} className="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-3.5">
                                    <div className="min-w-48 flex-1">
                                        <Link to={`/customer-payments?customer=${row.customer_id}`} className="font-medium hover:text-accent">
                                            {row.customer}
                                        </Link>
                                        <div className="mt-0.5 flex flex-wrap items-center gap-3 text-xs text-muted">
                                            <Link to={`/orders/${row.order_id}`} className="hover:text-ink">
                                                #{row.order_id}
                                            </Link>
                                            {row.phone && (
                                                <a href={`tel:${row.phone}`} className="inline-flex items-center gap-1 hover:text-ink" dir="ltr">
                                                    <Phone className="size-3" />
                                                    {row.phone}
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                    {row.late > 0 ? (
                                        <div className="text-sm">
                                            <Badge tone="danger">{t('admin.home.late_since', { date: date(row.late_since) })}</Badge>
                                            <p className="mt-1 font-semibold text-danger tabular-nums">{money(row.late)}</p>
                                        </div>
                                    ) : (
                                        row.next_due_date && (
                                            <div className="text-sm">
                                                <Badge tone={row.is_due_today ? 'warning' : 'gray'}>{t('admin.home.next_due', { date: date(row.next_due_date) })}</Badge>
                                                <p className="mt-1 font-semibold tabular-nums">{money(row.next_amount)}</p>
                                            </div>
                                        )
                                    )}
                                    <div className="text-end text-sm">
                                        <p className="text-xs text-muted">{t('admin.home.remaining_weeks', { weeks: row.weeks_left })}</p>
                                        <p className="mt-1 font-medium tabular-nums">{money(row.remaining)}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            )}
        </>
    );
}
