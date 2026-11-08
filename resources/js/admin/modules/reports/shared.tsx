import { MetricView } from '../../components/MetricCard';
import { useEffect, useState, type ReactNode } from 'react';
import { useSearchParams } from 'react-router';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { CalendarRange, Download, FileBarChart, RefreshCw } from 'lucide-react';
import { api, type Query } from '../../lib/api';
import { cn } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Button, Empty, Field, Input, Skeleton, Toggle, toneOf, type Tone } from '../../components/ui';

/** A total as the server works it out: its label, its raw value, its Filament color, and its formatted text. */
export interface ReportTotal {
    label: string;
    value: number;
    color: string;
    currency: string | null;
    formatted: { number: string; currency: string | null };
}

export interface ReportPayload<Row, Filters = Record<string, never>> {
    key: string;
    title: string;
    from: string;
    to: string;
    filters: Filters;
    columns: { name: string; label: string }[];
    rows: Row[];
    totals: ReportTotal[];
    excel_url: string;
}

const pad = (value: number) => String(value).padStart(2, '0');
export const isoDate = (value: Date) => `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;

/** The quick periods of the period bar, each worked out from today. */
function presets(): { key: string; from: string; to: string }[] {
    const today = new Date();
    const y = today.getFullYear();
    const m = today.getMonth();
    const threeMonthsAgo = new Date(today);
    threeMonthsAgo.setMonth(m - 3);
    return [
        {
            key: 'metrics.ranges.today',
            from: isoDate(today),
            to: isoDate(today),
        },
        {
            key: 'metrics.ranges.this_month',
            from: isoDate(new Date(y, m, 1)),
            to: isoDate(today),
        },
        {
            key: 'metrics.ranges.last_month',
            from: isoDate(new Date(y, m - 1, 1)),
            to: isoDate(new Date(y, m, 0)),
        },
        {
            key: 'admin/reports.period.last_three_months',
            from: isoDate(threeMonthsAgo),
            to: isoDate(today),
        },
        {
            key: 'metrics.ranges.this_year',
            from: isoDate(new Date(y, 0, 1)),
            to: isoDate(today),
        },
    ];
}

/**
 * The state of a report in the address: its period (from, to) and its own filters, so a report can be shared and
 * reopened as it was. Nothing in the address means the server's default, the last three months.
 */
export function useReportParams() {
    const [params, setParams] = useSearchParams();
    const update = (changes: Record<string, string | string[] | null>) =>
        setParams(
            (current) => {
                const next = new URLSearchParams(current);
                for (const [key, value] of Object.entries(changes)) {
                    next.delete(key);
                    next.delete(`${key}[]`);
                    if (Array.isArray(value)) value.forEach((item) => next.append(`${key}[]`, item));
                    else if (value !== null && value !== '') next.set(key, value);
                }
                return next;
            },
            { replace: true },
        );
    return { params, update };
}

/** Reads one report from the API with the period and filters of the address. */
export function useReport<T>(key: string, query: Query) {
    return useQuery({
        queryKey: ['reports', key, query],
        queryFn: () => api.get<T>(`/reports/${key}`, query),
        placeholderData: keepPreviousData,
    });
}

/**
 * The period of a report: a single day, or a period from one day to another, never after today, with quick periods.
 * What is typed is only a draft; "Fetch data" asks the server for it, as the panel's fetch button did.
 */
export function PeriodBar({ from, to, onFetch, fetching, excelUrl, children }: { from?: string; to?: string; onFetch: (period: { from: string; to: string }) => void; fetching?: boolean; excelUrl?: string; children?: ReactNode }) {
    const t = useT();
    const today = isoDate(new Date());
    const [draft, setDraft] = useState({ from: from ?? '', to: to ?? '' });
    const [single, setSingle] = useState(!!from && from === to);

    useEffect(() => {
        setDraft({ from: from ?? '', to: to ?? '' });
        setSingle(!!from && from === to);
    }, [from, to]);

    const until = single ? draft.from : draft.to;
    const incomplete = !draft.from || !until;
    const invalid = !incomplete && until < draft.from;

    return (
        <div className="card mb-4 flex flex-col gap-4 p-4">
            <div className="flex flex-wrap items-end gap-3">
                <Field label={single ? t('admin/reports.period.single_day') : t('admin.form.from')} className="w-full sm:w-44">
                    {(id) => <Input id={id} type="date" max={today} value={draft.from} onChange={(event) => setDraft({ ...draft, from: event.target.value })} />}
                </Field>
                {!single && (
                    <Field label={t('admin.form.until')} className="w-full sm:w-44" error={invalid ? t('filament/reports.period.incomplete') : undefined}>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                min={draft.from || undefined}
                                max={today}
                                value={draft.to}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        to: event.target.value,
                                    })
                                }
                            />
                        )}
                    </Field>
                )}
                <div className="pb-2">
                    <Toggle checked={single} onChange={setSingle} label={t('admin/reports.period.single_day')} />
                </div>
                {children}
                <div className="ms-auto flex flex-wrap items-center gap-2">
                    <Button variant="primary" icon={<RefreshCw className="size-4" />} loading={fetching} disabled={incomplete || invalid} onClick={() => onFetch({ from: draft.from, to: until })}>
                        {t('filament/reports.actions.fetch')}
                    </Button>
                    {excelUrl && (
                        <a href={excelUrl} className="inline-flex h-9.5 items-center gap-2 rounded-lg border border-line bg-surface px-3.5 text-sm font-medium text-success shadow-sm hover:bg-subtle">
                            <Download className="size-4" />
                            {t('filament/reports.actions.download_excel')}
                        </a>
                    )}
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-1.5 border-t border-line pt-3">
                <span className="me-1 inline-flex items-center gap-1 text-xs text-muted">
                    <CalendarRange className="size-3.5" />
                    {t('admin/reports.period.quick')}
                </span>
                {presets().map((preset) => (
                    <button
                        key={preset.key}
                        type="button"
                        onClick={() => onFetch({ from: preset.from, to: preset.to })}
                        className={cn('rounded-md px-2 py-1 text-xs font-medium transition-colors', preset.from === from && preset.to === to ? 'bg-accent-soft text-accent' : 'text-muted hover:bg-subtle hover:text-ink')}
                    >
                        {t(preset.key)}
                    </button>
                ))}
            </div>
        </div>
    );
}

const toneText: Record<Tone, string> = {
    gray: 'text-muted',
    accent: 'text-accent',
    success: 'text-success',
    warning: 'text-[oklch(0.6_0.14_65)]',
    danger: 'text-danger',
    info: 'text-info',
};

/** The text color of a Filament color name, for amounts. */
export function toneClass(color: string | null | undefined): string {
    return toneText[toneOf(color)];
}

/** The totals of a report: one card each, with its colored amount, as under the panel's tables. */
export function TotalCards({ totals, className }: { totals: ReportTotal[]; className?: string }) {
    if (totals.length === 0) return null;
    return (
        <div className={cn('grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4', className)}>
            {totals.map((total) => {
                const tone = toneOf(total.color);
                return (
                    <MetricView
                        key={total.label}
                        metric={{ label: total.label, value: { number: total.formatted.number, currency: total.formatted.currency }, range: '', ranges: [], explanation: '', example: '' }}
                        tone={tone === 'success' || tone === 'danger' ? tone : undefined}
                    />
                );
            })}
        </div>
    );
}

export interface ReportColumn<Row> {
    name: string;
    label: ReactNode;
    numeric?: boolean;
    render?: (row: Row) => ReactNode;
    className?: string | ((row: Row) => string);
}

/** A report table: all the rows of the period on one page, numbers at the end of the line, an empty state when there is nothing. */
export function ReportTable<Row>({
    columns,
    rows,
    loading,
    footer,
    rowKey,
    actions,
}: {
    columns: ReportColumn<Row>[];
    rows?: Row[];
    loading?: boolean;
    footer?: ReactNode;
    rowKey: (row: Row, index: number) => string | number;
    actions?: (row: Row) => ReactNode;
}) {
    const t = useT();
    if (!rows) {
        return (
            <div className="card space-y-2 p-4">
                {Array.from({ length: 6 }).map((_, index) => (
                    <Skeleton key={index} className="h-8 w-full" />
                ))}
            </div>
        );
    }
    return (
        <div className={cn('card overflow-hidden', loading && 'opacity-60 transition-opacity')}>
            {rows.length === 0 ? (
                <Empty icon={<FileBarChart className="size-5" />} title={t('filament/reports.empty.no_data')} />
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                                {columns.map((column) => (
                                    <th key={column.name} className={cn('px-4 py-2.5 font-medium whitespace-nowrap', column.numeric ? 'text-end' : 'text-start')}>
                                        {column.label}
                                    </th>
                                ))}
                                {actions && <th className="w-px px-4 py-2.5" />}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={rowKey(row, index)} className="border-b border-line last:border-0 hover:bg-subtle/50">
                                    {columns.map((column) => (
                                        <td key={column.name} className={cn('px-4 py-2.5', column.numeric && 'text-end tabular-nums whitespace-nowrap', typeof column.className === 'function' ? column.className(row) : column.className)}>
                                            {column.render ? column.render(row) : String((row as Record<string, unknown>)[column.name] ?? '—')}
                                        </td>
                                    ))}
                                    {actions && <td className="px-4 py-1.5 text-end whitespace-nowrap">{actions(row)}</td>}
                                </tr>
                            ))}
                        </tbody>
                        {footer && <tfoot className="border-t border-line">{footer}</tfoot>}
                    </table>
                </div>
            )}
        </div>
    );
}

/** The label the server gave a column, or the column name before the report is read. */
export function columnLabel(payload: { columns: { name: string; label: string }[] } | undefined, name: string, fallback: string): string {
    return payload?.columns.find((column) => column.name === name)?.label ?? fallback;
}
