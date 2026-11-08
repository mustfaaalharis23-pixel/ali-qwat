import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { number } from '../../lib/format';
import { useI18n, useT } from '../../lib/i18n';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import { columnLabel, PeriodBar, ReportTable, TotalCards, useReport, useReportParams, type ReportPayload } from './shared';

interface SalesRow {
    day: string;
    cash: number;
    postpaid: number;
    total: number;
}

/** The sales of each day, cash and postpaid stacked, so the shape of the period is seen before its numbers. */
function DailySalesChart({ rows, cashLabel, postpaidLabel }: { rows: SalesRow[]; cashLabel: string; postpaidLabel: string }) {
    const t = useT();
    const { dir } = useI18n();
    if (rows.length < 2) return null;
    return (
        <div className="card mb-4 p-4">
            <p className="mb-3 text-[13px] font-medium text-muted">{t('admin/reports.chart.daily_sales')}</p>
            <div className="h-64" dir="ltr">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={rows} margin={{ top: 4, right: 4, bottom: 0, left: 4 }} barCategoryGap="20%">
                        <CartesianGrid vertical={false} stroke="var(--color-line)" />
                        <XAxis dataKey="day" reversed={dir === 'rtl'} tickLine={false} axisLine={{ stroke: 'var(--color-line)' }} tick={{ fill: 'var(--color-muted)', fontSize: 11 }} minTickGap={12} />
                        <YAxis
                            orientation={dir === 'rtl' ? 'right' : 'left'}
                            tickLine={false}
                            axisLine={false}
                            width={56}
                            tick={{ fill: 'var(--color-muted)', fontSize: 11 }}
                            tickFormatter={(value: number) =>
                                new Intl.NumberFormat('en-US', {
                                    notation: 'compact',
                                }).format(value)
                            }
                        />
                        <Tooltip
                            cursor={{ fill: 'var(--color-subtle)' }}
                            formatter={(value) => number(Number(value))}
                            contentStyle={{
                                background: 'var(--color-surface)',
                                border: '1px solid var(--color-line)',
                                borderRadius: 8,
                                fontSize: 12,
                                color: 'var(--color-ink)',
                            }}
                            labelStyle={{ color: 'var(--color-muted)' }}
                        />
                        <Legend
                            iconType="circle"
                            iconSize={8}
                            wrapperStyle={{
                                fontSize: 12,
                                color: 'var(--color-muted)',
                            }}
                        />
                        <Bar dataKey="cash" name={cashLabel} stackId="sales" fill="var(--color-success)" stroke="var(--color-surface)" strokeWidth={1} maxBarSize={28} />
                        <Bar dataKey="postpaid" name={postpaidLabel} stackId="sales" fill="var(--color-warning)" stroke="var(--color-surface)" strokeWidth={1} radius={[4, 4, 0, 0]} maxBarSize={28} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export function SalesReportPage() {
    const t = useT();
    const { params, update } = useReportParams();
    const query = { from: params.get('from'), to: params.get('to') };
    const { data, error, isFetching } = useReport<ReportPayload<SalesRow>>('sales', query);

    if (error) return <QueryError error={error} />;

    const cash = columnLabel(data, 'cash', 'cash');
    const postpaid = columnLabel(data, 'postpaid', 'postpaid');

    return (
        <>
            <PageHeader title={data?.title ?? t('filament/reports/sales.title')} description={t('admin/reports.descriptions.sales')} />
            <PeriodBar from={data?.from} to={data?.to} fetching={isFetching} excelUrl={data?.excel_url} onFetch={(period) => update(period)} />
            {data && <DailySalesChart rows={data.rows} cashLabel={cash} postpaidLabel={postpaid} />}
            <ReportTable
                rows={data?.rows}
                loading={isFetching}
                rowKey={(row) => row.day}
                columns={[
                    {
                        name: 'day',
                        label: columnLabel(data, 'day', t('filament/reports/sales.columns.day')),
                        className: 'tabular-nums',
                    },
                    {
                        name: 'cash',
                        label: cash,
                        numeric: true,
                        className: 'text-success',
                        render: (row) => number(row.cash),
                    },
                    {
                        name: 'postpaid',
                        label: postpaid,
                        numeric: true,
                        className: 'text-[oklch(0.6_0.14_65)]',
                        render: (row) => number(row.postpaid),
                    },
                    {
                        name: 'total',
                        label: columnLabel(data, 'total', t('filament/reports/sales.columns.total')),
                        numeric: true,
                        className: 'font-semibold',
                        render: (row) => number(row.total),
                    },
                ]}
            />
            {data && <TotalCards totals={data.totals} className="mt-4 xl:grid-cols-3" />}
        </>
    );
}
