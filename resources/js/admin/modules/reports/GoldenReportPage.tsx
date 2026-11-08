import { useEffect, useState } from 'react';
import { Trophy } from 'lucide-react';
import { cn, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Field, Input } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import { columnLabel, PeriodBar, ReportTable, useReport, useReportParams, type ReportPayload } from './shared';

interface GoldenRow {
    rank: number;
    item: string;
    lists: string;
    quantity: number;
    amount: number;
    sales_share: number;
    profit: number;
    margin: number;
    profit_share: number;
}

interface GoldenPayload extends ReportPayload<GoldenRow, { lists: string[]; count: number }> {
    summary: Record<string, number> | null;
    summary_text: Record<'period_profit' | 'items_profit' | 'period_sales' | 'items_sales' | 'profit_share' | 'sales_share' | 'items_margin', string> | null;
}

const LISTS = ['profit', 'quantity', 'amount'] as const;

export function GoldenReportPage() {
    const t = useT();
    const { params, update } = useReportParams();
    const asked = params.getAll('lists[]').filter((list) => (LISTS as readonly string[]).includes(list));
    const lists = asked.length > 0 ? asked : [...LISTS];
    const count = params.get('count') ?? '10';
    const query = {
        from: params.get('from'),
        to: params.get('to'),
        lists,
        count,
    };
    const { data, error, isFetching } = useReport<GoldenPayload>('golden', query);
    const [countDraft, setCountDraft] = useState(count);

    useEffect(() => {
        setCountDraft(count);
    }, [count]);

    if (error) return <QueryError error={error} />;

    const percent = t('filament/reports/golden.percent');
    const share = (value: number) => `${number(value, 1)}${percent}`;
    const toggleList = (list: string) => {
        const next = lists.includes(list) ? lists.filter((item) => item !== list) : [...lists, list];
        if (next.length > 0) update({ lists: LISTS.filter((item) => next.includes(item)) });
    };
    const applyCount = () => {
        const parsed = Math.max(1, Math.min(100, Number.parseInt(countDraft, 10) || 10));
        setCountDraft(String(parsed));
        update({ count: String(parsed) });
    };
    const signed = (value: number) => (value < 0 ? 'text-danger' : 'text-success');
    const summary = data?.summary_text;

    return (
        <>
            <PageHeader title={data?.title ?? t('filament/reports/golden.title')} description={t('admin/reports.descriptions.golden')} />
            <PeriodBar from={data?.from} to={data?.to} fetching={isFetching} excelUrl={data?.excel_url} onFetch={(period) => update(period)}>
                <Field label={t('filament/reports/golden.lists.label')}>
                    {() => (
                        <div className="inline-flex max-w-full gap-1 overflow-x-auto rounded-lg bg-subtle p-1" role="group">
                            {LISTS.map((list) => (
                                <button
                                    key={list}
                                    type="button"
                                    aria-pressed={lists.includes(list)}
                                    onClick={() => toggleList(list)}
                                    className={cn('rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', lists.includes(list) ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink')}
                                >
                                    {t(`filament/reports/golden.lists.${list}`)}
                                </button>
                            ))}
                        </div>
                    )}
                </Field>
                <Field label={t('filament/reports/golden.count.label')} className="w-28">
                    {(id) => <Input id={id} type="number" min={1} max={100} value={countDraft} onChange={(event) => setCountDraft(event.target.value)} onBlur={applyCount} onKeyDown={(event) => event.key === 'Enter' && applyCount()} />}
                </Field>
            </PeriodBar>
            <ReportTable
                rows={data?.rows}
                loading={isFetching}
                rowKey={(row) => `${row.rank}-${row.item}`}
                columns={[
                    {
                        name: 'rank',
                        label: '#',
                        render: (row) => (
                            <Badge tone={row.rank <= 3 ? 'warning' : 'gray'}>
                                {row.rank <= 3 ? <Trophy className="size-3" /> : null}
                                {row.rank}
                            </Badge>
                        ),
                    },
                    {
                        name: 'item',
                        label: columnLabel(data, 'item', t('filament/reports/golden.columns.item')),
                        className: 'font-medium',
                    },
                    {
                        name: 'lists',
                        label: columnLabel(data, 'lists', t('filament/reports/golden.columns.lists')),
                        className: 'text-xs text-muted',
                    },
                    {
                        name: 'quantity',
                        label: columnLabel(data, 'quantity', t('filament/reports/golden.columns.quantity')),
                        numeric: true,
                        render: (row) => number(row.quantity),
                    },
                    {
                        name: 'amount',
                        label: columnLabel(data, 'amount', t('filament/reports/golden.columns.amount')),
                        numeric: true,
                        render: (row) => number(row.amount),
                    },
                    {
                        name: 'sales_share',
                        label: columnLabel(data, 'sales_share', t('filament/reports/golden.columns.sales_share')),
                        numeric: true,
                        className: 'text-info',
                        render: (row) => share(row.sales_share),
                    },
                    {
                        name: 'profit',
                        label: columnLabel(data, 'profit', t('filament/reports/golden.columns.profit')),
                        numeric: true,
                        className: (row) => cn('font-semibold', signed(row.profit)),
                        render: (row) => number(row.profit),
                    },
                    {
                        name: 'margin',
                        label: columnLabel(data, 'margin', t('filament/reports/golden.columns.margin')),
                        numeric: true,
                        className: (row) => signed(row.margin),
                        render: (row) => share(row.margin),
                    },
                    {
                        name: 'profit_share',
                        label: columnLabel(data, 'profit_share', t('filament/reports/golden.columns.profit_share')),
                        numeric: true,
                        className: 'font-semibold text-[oklch(0.6_0.14_65)]',
                        render: (row) => share(row.profit_share),
                    },
                ]}
                footer={
                    summary && (
                        <tr>
                            <td colSpan={9} className="px-4 py-3.5 text-center">
                                <div className="flex flex-wrap items-baseline justify-center gap-x-2 gap-y-1 text-[15px]">
                                    <span className="text-muted">{t('filament/reports/golden.summary.period_profit')}</span>
                                    <span className="font-semibold text-success tabular-nums">{summary.period_profit}</span>
                                    <span className="text-faint">·</span>
                                    <span className="text-muted">{t('filament/reports/golden.summary.profit_share')}</span>
                                    <span className="font-semibold text-[oklch(0.6_0.14_65)] tabular-nums">{summary.profit_share}</span>
                                    <span className="text-muted tabular-nums">({summary.items_profit})</span>
                                </div>
                                <div className="mt-1.5 flex flex-wrap items-baseline justify-center gap-x-2 gap-y-1 text-xs text-muted">
                                    <span>
                                        {t('filament/reports/golden.summary.period_sales')} <span className="tabular-nums">{summary.period_sales}</span>
                                    </span>
                                    <span>·</span>
                                    <span>
                                        {t('filament/reports/golden.summary.items_sales')}{' '}
                                        <span className="tabular-nums">
                                            {summary.items_sales} ({summary.sales_share})
                                        </span>
                                    </span>
                                    <span>·</span>
                                    <span>
                                        {t('filament/reports/golden.summary.items_margin')} <span className="tabular-nums">{summary.items_margin}</span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    )
                }
            />
        </>
    );
}
