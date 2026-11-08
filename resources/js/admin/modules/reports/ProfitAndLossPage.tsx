import { useEffect, useRef, useState, type ReactNode } from 'react';
import { CalendarDays, ChevronDown, ChevronLeft, ChevronRight, Download, ExternalLink, Undo2 } from 'lucide-react';
import { cn, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Button, Empty, Skeleton } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import { TotalCards, useReport, useReportParams, type ReportTotal } from './shared';

interface CompanyValue {
    moment: string;
    parts: { key: string; amount: number }[];
    total: number;
}

interface Statement {
    title: string;
    subheading: string;
    month: string;
    current_month: string;
    first_year: number;
    month_names: string[];
    has_exclusions: boolean;
    cards: ReportTotal[];
    revenue: {
        sales_profit: number;
        external: {
            id: number;
            name: string;
            amount: number;
            is_excluded: boolean;
        }[];
        total: number;
    };
    expenses: {
        groups: {
            id: number;
            name: string;
            amount: number;
            is_excluded: boolean;
            lines: {
                name: string;
                amount: number;
                invoice_id: number;
                invoice_name: string;
                invoice_date: string;
                invoice_path: string;
            }[];
        }[];
        total: number;
    };
    summary: {
        net_profit: number;
        capital_movement: number;
        value_at_start: CompanyValue;
        computed_value: number;
        value_at_end: CompanyValue;
        difference: number;
        checks_itself: boolean;
        is_matched: boolean;
    };
    excel_url: string;
}

/** An amount the way an accountant reads it: a whole dinar, and a negative between brackets. */
function accounting(amount: number): string {
    const text = number(Math.round(Math.abs(amount)), 0);
    return amount < 0 ? `(${text})` : text;
}

/**
 * The period of the report: one whole month. The year is walked with the arrows, the months are a grid, the month that
 * is running has a dashed border, and a month that has not begun cannot be chosen. Choosing a month fetches it at once.
 */
function MonthPicker({ month, currentMonth, firstYear, monthNames, onChoose }: { month: string; currentMonth: string; firstYear: number; monthNames: string[]; onChoose: (month: string) => void }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [year, setYear] = useState(Number(month.slice(0, 4)));
    const box = useRef<HTMLDivElement>(null);
    const currentYear = Number(currentMonth.slice(0, 4));
    const label = (value: string) => `${monthNames[Number(value.slice(5, 7)) - 1]} ${value.slice(0, 4)}`;
    const shift = (value: string, months: number) => {
        const date = new Date(Number(value.slice(0, 4)), Number(value.slice(5, 7)) - 1 + months, 1);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    };
    const choose = (value: string) => {
        setOpen(false);
        onChoose(value);
    };

    useEffect(() => {
        if (!open) return;
        setYear(Number(month.slice(0, 4)));
        const close = (event: MouseEvent) => box.current && !box.current.contains(event.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open, month]);

    return (
        <div ref={box} className="relative w-full max-w-xs">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                aria-label={`${t('filament/reports/profit_and_loss.picker.label')}: ${label(month)}`}
                className="card flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-start hover:bg-subtle"
            >
                <span className="flex items-center gap-2.5">
                    <CalendarDays className="size-4 text-muted" />
                    <span>
                        <span className="block text-[11px] text-muted">{t('filament/reports/profit_and_loss.picker.label')}</span>
                        <span className="text-sm font-semibold">{label(month)}</span>
                    </span>
                </span>
                <ChevronDown className={cn('size-4 text-muted transition-transform', open && 'rotate-180')} />
            </button>
            {open && (
                <div className="card absolute start-0 top-full z-30 mt-1.5 w-full p-3 shadow-lg">
                    <div className="mb-2 flex items-center justify-between">
                        <button
                            type="button"
                            disabled={year <= firstYear}
                            onClick={() => setYear(year - 1)}
                            className="rounded-md p-1.5 text-muted hover:bg-subtle hover:text-ink disabled:opacity-30"
                            aria-label={t('filament/reports/profit_and_loss.picker.earlier_year')}
                        >
                            <ChevronLeft className="size-4 rtl:rotate-180" />
                        </button>
                        <span className="text-sm font-semibold tabular-nums">{year}</span>
                        <button
                            type="button"
                            disabled={year >= currentYear}
                            onClick={() => setYear(year + 1)}
                            className="rounded-md p-1.5 text-muted hover:bg-subtle hover:text-ink disabled:opacity-30"
                            aria-label={t('filament/reports/profit_and_loss.picker.later_year')}
                        >
                            <ChevronRight className="size-4 rtl:rotate-180" />
                        </button>
                    </div>
                    <div className="mb-2 flex gap-1.5">
                        <Button size="sm" className="flex-1" onClick={() => choose(shift(currentMonth, -1))}>
                            {t('filament/reports/profit_and_loss.picker.last_month')}
                        </Button>
                        <Button size="sm" className="flex-1" onClick={() => choose(currentMonth)}>
                            {t('filament/reports/profit_and_loss.picker.this_month')}
                        </Button>
                    </div>
                    <div className="grid grid-cols-3 gap-1.5">
                        {monthNames.map((name, index) => {
                            const value = `${year}-${String(index + 1).padStart(2, '0')}`;
                            const future = value > currentMonth;
                            return (
                                <button
                                    key={value}
                                    type="button"
                                    disabled={future}
                                    onClick={() => choose(value)}
                                    className={cn(
                                        'flex flex-col items-center rounded-lg border px-1 py-1.5 text-[13px] transition-colors disabled:cursor-not-allowed disabled:opacity-35',
                                        value === month ? 'border-accent bg-accent text-white' : value === currentMonth ? 'border-dashed border-accent text-ink hover:bg-subtle' : 'border-transparent hover:bg-subtle',
                                    )}
                                >
                                    <span className="font-medium">{name}</span>
                                    <span className={cn('num text-[11px]', value === month ? 'text-white/80' : 'text-faint')}>{String(index + 1).padStart(2, '0')}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

/** One side of the statement: a colored head, its rows, and its total. */
function Side({ title, tone, total, children }: { title: string; tone: 'success' | 'danger'; total: ReactNode; children: ReactNode }) {
    const t = useT();
    return (
        <div className="card overflow-hidden">
            <div className="flex items-center justify-between border-b border-line px-5 py-3.5">
                <span className="flex items-center gap-2.5 text-[14.5px] font-semibold">
                    <span className={cn('size-2 rounded-full', tone === 'success' ? 'bg-success' : 'bg-danger')} />
                    {title}
                </span>
                <span className="text-xs text-muted">{t('metrics.currencies.IQD')}</span>
            </div>
            <table className="w-full text-sm">
                <tbody>{children}</tbody>
                <tfoot>
                    <tr className="border-t border-line bg-subtle/50">
                        <td className="w-10" />
                        <td className="px-4 py-2.5 font-semibold">{t(`filament/reports/profit_and_loss.rows.total_${tone === 'success' ? 'revenue' : 'expenses'}`)}</td>
                        <td className={cn('px-4 py-2.5 text-end num text-[15px] font-semibold whitespace-nowrap', tone === 'success' ? 'text-success' : 'text-danger')}>{total}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

/** The box at the start of a line: ticked means counted in the totals, unticked means left out. */
function IncludeBox({ name, included, onToggle }: { name: string; included: boolean; onToggle: () => void }) {
    const t = useT();
    return (
        <td className="w-10 ps-3 align-middle">
            <input type="checkbox" checked={included} onChange={onToggle} className="size-4 cursor-pointer accent-[var(--color-accent)]" aria-label={t('filament/reports/profit_and_loss.exclusions.include', { name })} />
        </td>
    );
}

function ExcludedBadge() {
    const t = useT();
    return (
        <Badge tone="warning" className="ms-1.5">
            {t('filament/reports/profit_and_loss.exclusions.excluded')}
        </Badge>
    );
}

/** What a value of the company is made of: every part with the amount it adds, then the value itself. */
function CompanyValueParts({ value }: { value: CompanyValue }) {
    const t = useT();
    return (
        <div className="space-y-2">
            <p className="text-xs text-muted">
                {t('filament/reports/profit_and_loss.value.moment', {
                    moment: value.moment,
                })}
            </p>
            <table className="w-full text-[13px]">
                <tbody>
                    {value.parts.map((part) => (
                        <tr key={part.key} className="border-b border-line">
                            <td className="py-1.5">{t(`filament/reports/profit_and_loss.value.${part.key}`)}</td>
                            <td className={cn('py-1.5 text-end num whitespace-nowrap', part.amount < 0 ? 'text-danger' : 'text-muted')}>{number(Math.round(part.amount), 0)}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr>
                        <td className="pt-2 font-semibold">{t('filament/reports/profit_and_loss.value.total')}</td>
                        <td className="pt-2 text-end num font-semibold whitespace-nowrap text-info">
                            {number(Math.round(value.total), 0)} {t('metrics.currencies.IQD')}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

export function ProfitAndLossPage() {
    const t = useT();
    const { params, update } = useReportParams();
    const excludedRevenues = params.getAll('excluded_revenues[]');
    const excludedExpenses = params.getAll('excluded_expenses[]');
    const query = {
        month: params.get('month'),
        excluded_revenues: excludedRevenues,
        excluded_expenses: excludedExpenses,
    };
    const { data, error, isFetching } = useReport<Statement>('profit-and-loss', query);
    const [openGroups, setOpenGroups] = useState<number[]>([]);
    const [openValue, setOpenValue] = useState<'start' | 'end' | null>(null);

    if (error) return <QueryError error={error} />;

    const toggled = (list: string[], id: number) => (list.includes(String(id)) ? list.filter((item) => item !== String(id)) : [...list, String(id)]);
    const arrow = (open: boolean) => <ChevronRight className={cn('inline size-3.5 text-faint transition-transform', open ? 'rotate-90' : 'rtl:rotate-180')} />;
    const summary = data?.summary;
    const matching = !summary ? 'gray' : !summary.checks_itself ? 'gray' : summary.is_matched ? 'success' : 'danger';
    const matchingText = !summary ? '' : summary.checks_itself ? t(`filament/reports/profit_and_loss.rows.${summary.is_matched ? 'matched' : 'not_matched'}`) : t('filament/reports/profit_and_loss.exclusions.not_checked');
    const matchingClass = {
        gray: 'text-muted bg-subtle',
        success: 'text-success bg-success-soft/50',
        danger: 'text-danger bg-danger-soft/50',
    }[matching];
    const afterExclusions = summary && !summary.checks_itself ? <Badge tone="warning">{t('filament/reports/profit_and_loss.exclusions.after')}</Badge> : null;

    return (
        <>
            <PageHeader
                title={data?.title ?? t('filament/reports/profit_and_loss.title')}
                description={
                    <>
                        {data && <span className="me-1.5 font-medium text-ink">{data.subheading} ·</span>}
                        {t('admin/reports.descriptions.profit_and_loss')}
                    </>
                }
                actions={
                    <>
                        {data?.has_exclusions && (
                            <Button
                                icon={<Undo2 className="size-4" />}
                                onClick={() =>
                                    update({
                                        excluded_revenues: [],
                                        excluded_expenses: [],
                                    })
                                }
                            >
                                {t('filament/reports/profit_and_loss.exclusions.clear')}
                            </Button>
                        )}
                        {data && (
                            <a href={data.excel_url} className="inline-flex h-9.5 items-center gap-2 rounded-lg border border-line bg-surface px-3.5 text-sm font-medium text-success shadow-sm hover:bg-subtle">
                                <Download className="size-4" />
                                {t('filament/reports.actions.download_excel')}
                            </a>
                        )}
                    </>
                }
            />

            {!data ? (
                <div className="space-y-4">
                    <Skeleton className="h-14 w-72" />
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <Skeleton key={index} className="h-24 rounded-[var(--radius-card)]" />
                        ))}
                    </div>
                    <Skeleton className="h-72 rounded-[var(--radius-card)]" />
                </div>
            ) : (
                <div className={cn('space-y-4', isFetching && 'opacity-60 transition-opacity')}>
                    <MonthPicker month={data.month} currentMonth={data.current_month} firstYear={data.first_year} monthNames={data.month_names} onChoose={(month) => update({ month })} />
                    <TotalCards totals={data.cards} />

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Side title={t('filament/reports/profit_and_loss.sections.revenue')} tone="success" total={accounting(data.revenue.total)}>
                            <tr>
                                <td className="w-10" />
                                <td className="px-4 py-2.5">{t('filament/reports/profit_and_loss.rows.sales_profit')}</td>
                                <td className="px-4 py-2.5 text-end num whitespace-nowrap text-success">{accounting(data.revenue.sales_profit)}</td>
                            </tr>
                            {data.revenue.external.map((revenue) => (
                                <tr key={revenue.id} className="border-t border-line">
                                    <IncludeBox
                                        name={revenue.name}
                                        included={!revenue.is_excluded}
                                        onToggle={() =>
                                            update({
                                                excluded_revenues: toggled(excludedRevenues, revenue.id),
                                            })
                                        }
                                    />
                                    <td className="px-4 py-2.5">
                                        <span className={cn(revenue.is_excluded && 'line-through opacity-50')}>{revenue.name}</span>
                                        {revenue.is_excluded && <ExcludedBadge />}
                                    </td>
                                    <td className={cn('px-4 py-2.5 text-end num whitespace-nowrap', revenue.is_excluded ? 'text-muted line-through opacity-50' : 'text-success')}>{accounting(revenue.amount)}</td>
                                </tr>
                            ))}
                        </Side>

                        <Side title={t('filament/reports/profit_and_loss.sections.expenses')} tone="danger" total={accounting(data.expenses.total)}>
                            {data.expenses.groups.length === 0 && (
                                <tr>
                                    <td colSpan={3}>
                                        <Empty title={t('filament/reports.empty.no_data')} />
                                    </td>
                                </tr>
                            )}
                            {data.expenses.groups.map((group, index) => {
                                const open = openGroups.includes(group.id);
                                return [
                                    <tr key={`group-${group.id}`} className={cn(index > 0 && 'border-t border-line')}>
                                        <IncludeBox
                                            name={group.name}
                                            included={!group.is_excluded}
                                            onToggle={() =>
                                                update({
                                                    excluded_expenses: toggled(excludedExpenses, group.id),
                                                })
                                            }
                                        />
                                        <td className="px-4 py-2.5">
                                            <button
                                                type="button"
                                                aria-expanded={open}
                                                onClick={() => setOpenGroups(open ? openGroups.filter((id) => id !== group.id) : [...openGroups, group.id])}
                                                className="flex items-center gap-1.5 text-start font-medium hover:text-accent"
                                            >
                                                {arrow(open)}
                                                <span className={cn(group.is_excluded && 'line-through opacity-50')}>{group.name}</span>
                                                <span className="rounded bg-subtle px-1.5 text-[11px] text-muted">{group.lines.length}</span>
                                            </button>
                                            {group.is_excluded && <ExcludedBadge />}
                                        </td>
                                        <td className={cn('px-4 py-2.5 text-end num font-medium whitespace-nowrap', group.is_excluded ? 'text-muted line-through opacity-50' : 'text-danger')}>{accounting(group.amount)}</td>
                                    </tr>,
                                    ...(open
                                        ? group.lines.map((line, lineIndex) => (
                                              <tr key={`line-${group.id}-${lineIndex}`} className="border-t border-line bg-subtle/60 text-[13px]">
                                                  <td className="w-10" />
                                                  <td className="py-2 ps-10 pe-4">
                                                      <span className="me-1 text-faint">└</span>
                                                      {line.name}
                                                      <a
                                                          href={`/app${line.invoice_path}`}
                                                          target="_blank"
                                                          rel="noreferrer"
                                                          title={line.invoice_name}
                                                          className="ms-1.5 inline-flex items-center gap-1 rounded-md bg-accent-soft px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap text-accent hover:underline"
                                                      >
                                                          {t('filament/reports/profit_and_loss.rows.invoice')} #{line.invoice_id}
                                                          <ExternalLink className="size-3" />
                                                      </a>
                                                      <span className="ms-1.5 num text-[11px] text-faint">{line.invoice_date}</span>
                                                  </td>
                                                  <td className="px-4 py-2 text-end num whitespace-nowrap text-muted">{accounting(line.amount)}</td>
                                              </tr>
                                          ))
                                        : []),
                                ];
                            })}
                        </Side>
                    </div>

                    {summary && (
                        <div className="card overflow-hidden">
                            <div className="flex items-center justify-between border-b border-line px-5 py-3.5">
                                <span className="flex items-center gap-2.5 text-[14.5px] font-semibold">
                                    <span className="size-2 rounded-full bg-accent" />{t('filament/reports/profit_and_loss.sections.summary')}</span>
                                <span className="flex items-center gap-2">
                                    {afterExclusions}
                                    <span className="text-xs text-muted">{t('metrics.currencies.IQD')}</span>
                                </span>
                            </div>
                            <table className="w-full text-sm">
                                <tbody>
                                    <tr>
                                        <td className="px-4 py-3 font-semibold">
                                            {t('filament/reports/profit_and_loss.rows.net_profit')} {afterExclusions}
                                        </td>
                                        <td className={cn('px-4 py-3 text-end num text-base font-semibold whitespace-nowrap', summary.net_profit < 0 ? 'text-danger' : 'text-success')}>{accounting(summary.net_profit)}</td>
                                    </tr>
                                    <tr className="border-t border-line">
                                        <td className="px-4 py-2.5">{t('filament/reports/profit_and_loss.rows.capital_movement')}</td>
                                        <td className="px-4 py-2.5 text-end num whitespace-nowrap text-muted">{accounting(summary.capital_movement)}</td>
                                    </tr>
                                    {(
                                        [
                                            ['start', 'value_at_start', summary.value_at_start],
                                            ['computed', 'computed_value', null],
                                            ['end', 'value_at_end', summary.value_at_end],
                                        ] as const
                                    ).map(([key, label, value]) =>
                                        value === null ? (
                                            <tr key={key} className="border-t border-line bg-warning-soft/35">
                                                <td className="px-4 py-2.5 font-semibold">{t(`filament/reports/profit_and_loss.rows.${label}`)}</td>
                                                <td className="px-4 py-2.5 text-end num font-semibold whitespace-nowrap text-[oklch(0.6_0.14_65)]">{accounting(summary.computed_value)}</td>
                                            </tr>
                                        ) : (
                                            [
                                                <tr key={key} className="border-t border-line">
                                                    <td className="px-4 py-2.5">
                                                        <button type="button" aria-expanded={openValue === key} onClick={() => setOpenValue(openValue === key ? null : key)} className="flex items-center gap-1.5 text-start hover:text-accent">
                                                            {arrow(openValue === key)}
                                                            {t(`filament/reports/profit_and_loss.rows.${label}`)}
                                                        </button>
                                                    </td>
                                                    <td className="px-4 py-2.5 text-end num font-medium whitespace-nowrap text-info">{accounting(value.total)}</td>
                                                </tr>,
                                                openValue === key && (
                                                    <tr key={`${key}-parts`} className="border-t border-line bg-subtle/60">
                                                        <td colSpan={2} className="px-4 py-3 ps-10">
                                                            <CompanyValueParts value={value} />
                                                        </td>
                                                    </tr>
                                                ),
                                            ]
                                        ),
                                    )}
                                    <tr className={cn('border-t border-line', matchingClass)}>
                                        <td className="px-4 py-3 text-base font-semibold">{t('filament/reports/profit_and_loss.rows.matching')}</td>
                                        <td className="px-4 py-3 text-end text-base font-semibold">{matchingText}</td>
                                    </tr>
                                    <tr className="border-t border-line">
                                        <td className="px-4 py-2.5 font-medium">{t('filament/reports/profit_and_loss.rows.difference')}</td>
                                        <td className={cn('px-4 py-2.5 text-end num font-semibold whitespace-nowrap', matching === 'success' ? 'text-success' : matching === 'danger' ? 'text-danger' : 'text-muted')}>
                                            {accounting(summary.difference)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </>
    );
}
