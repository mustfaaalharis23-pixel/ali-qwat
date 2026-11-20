import { useMemo, useRef, useState, type ReactNode } from 'react';
import { CircleCheck, CircleX, FileSpreadsheet, Info, PenLine, TriangleAlert, Zap } from 'lucide-react';
import { api, ApiError } from '../../lib/api';
import { cn, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { ExcelTemplateDownload } from '../../components/ExcelTemplateDownload';
import { Badge, Button, Modal, toast } from '../../components/ui';
import { SearchSelect } from '../../components/SearchSelect';
import { yuan } from './shared';

type Level = 'error' | 'warning';

interface ExcelIssue {
    type: string;
    level: Level;
    row: number | null;
    values: Record<string, unknown>;
    message: string;
}

interface ExcelLine {
    code: string;
    rows: number[];
    item: { id: number; name: string; specific_description_id: number | null } | null;
    packets: number | null;
    pieces_per_packet: number | null;
    boxes_per_carton: number | null;
    cartons: number | null;
    carton_cbm: number | null;
    unit_cost: number | null;
    amount: number | null;
    colors: { name: string; pieces: number; color_id: number | null }[];
    groups: { power: number; packets: number; lens_power_id: number | null }[];
    issues: ExcelIssue[];
}

interface ExcelResult {
    lines: ExcelLine[];
    issues: ExcelIssue[];
    summary: {
        written: { packets: number | null; cartons: number | null; amount: number | null };
        read: { items: number; rows: number; packets: number | null; cartons: number | null; pieces: number | null; amount: number | null };
    };
}

/** A line of the file as the invoice's form takes it, once its item and colors are chosen. */
export interface ImportedLine {
    source_rows: string;
    specific_description_id: number | null;
    description_label: string | null;
    boxes_count: number | null;
    pieces_per_packet: number | null;
    boxes_per_carton: number | null;
    carton_cbm: number | null;
    unit_cost: number | null;
    colors: { color_id: number | null; color_label: string; pieces: number }[];
    groups: { lens_power_id: number | null; lens_power_label: string; packets: number }[];
}

interface Choice {
    id: number;
    label: string;
}

const ITEM_ISSUES = ['item_not_found', 'item_ambiguous', 'missing_item_code'];
const itemKey = (line: ExcelLine) => (line.code ? `code:${line.code}` : `row:${line.rows[0]}`);
const colorKey = (name: string) => `color:${name.trim().toLowerCase()}`;
const inHundreds = (power: number) => String(Math.round(power * 100));
/** The rows of a line as a range when they follow each other (4–5), or listed. */
const rowsText = (rows: number[]) => (rows.length > 1 && rows.every((row, index) => index === 0 || row === rows[index - 1] + 1) ? `${rows[0]}–${rows[rows.length - 1]}` : rows.join(', '));

/**
 * "Upload from Excel" on a new purchase invoice: the supplier's order is read on the server, reviewed here (its totals against
 * what was read, the items and colors to match, and what is wrong in each line), and only then goes into the form's lines.
 */
export function ExcelImport({ onFill, trigger }: { onFill: (lines: ImportedLine[]) => void; trigger?: (choose: () => void, reading: boolean) => ReactNode }) {
    const t = useT();
    const input = useRef<HTMLInputElement>(null);
    const [reading, setReading] = useState(false);
    const [review, setReview] = useState<{ name: string; result: ExcelResult } | null>(null);

    const read = async (file: File) => {
        setReading(true);
        try {
            const body = new FormData();
            body.append('file', file);
            setReview({ name: file.name, result: await api.post<ExcelResult>('/purchase-invoices/excel', body) });
        } catch (error) {
            toast.error(error instanceof ApiError ? (error.first() ?? error.message) : String(error));
        } finally {
            setReading(false);
            if (input.current) input.current.value = '';
        }
    };

    return (
        <>
            <input
                ref={input}
                type="file"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) void read(file);
                }}
            />
            {trigger ? (
                trigger(() => input.current?.click(), reading)
            ) : (
                <div className="flex flex-wrap gap-2">
                    <ExcelTemplateDownload endpoint="/purchase-invoices/excel/template" translationKey="admin/purchases.excel" small />
                    <Button size="sm" icon={<FileSpreadsheet className="size-4" />} loading={reading} onClick={() => input.current?.click()}>
                        {t('admin/purchases.excel.upload')}
                    </Button>
                </div>
            )}
            {review && (
                <ExcelReview
                    name={review.name}
                    result={review.result}
                    onClose={() => setReview(null)}
                    onFill={(lines) => {
                        onFill(lines);
                        setReview(null);
                    }}
                />
            )}
        </>
    );
}

function ExcelReview({ name, result, onClose, onFill }: { name: string; result: ExcelResult; onClose: () => void; onFill: (lines: ImportedLine[]) => void }) {
    const t = useT();
    const label = (key: string, replace?: Record<string, string | number>) => t(`admin/purchases.excel.${key}`, replace);
    const [items, setItems] = useState<Record<string, Choice>>({});
    const [colors, setColors] = useState<Record<string, Choice>>({});

    const unknownItems = useMemo(() => [...new Map(result.lines.filter((line) => line.item?.specific_description_id == null).map((line) => [itemKey(line), line])).entries()], [result]);
    const unknownColors = useMemo(() => {
        const names = new Map<string, string>();
        for (const line of result.lines) for (const color of line.colors) if (color.color_id === null && !names.has(colorKey(color.name))) names.set(colorKey(color.name), color.name);
        return [...names.entries()];
    }, [result]);

    /** An issue the choices made here have already answered: an item or a color now matched. */
    const answered = (line: ExcelLine, issue: ExcelIssue) =>
        (ITEM_ISSUES.includes(issue.type) && items[itemKey(line)] !== undefined) || (issue.type === 'color_not_found' && colors[colorKey(String(issue.values.name))] !== undefined);
    const openIssues = (line: ExcelLine) => line.issues.filter((issue) => !answered(line, issue));
    const allIssues = [...result.issues, ...result.lines.flatMap(openIssues)];
    const errorCount = allIssues.filter((issue) => issue.level === 'error').length;
    const warningCount = allIssues.length - errorCount;
    const hasWrittenTotals = Object.values(result.summary.written).some((value) => value !== null);
    const footerNote = errorCount > 0 ? label('fixed_in_form') : result.lines.every((line) => line.carton_cbm === null) ? label('cbm_note') : null;

    const fill = () =>
        onFill(
            result.lines.map((line) => ({
                source_rows: rowsText(line.rows),
                specific_description_id: line.issues.some((issue) => issue.type === 'packet_differs_from_item') ? null : (line.item?.specific_description_id ?? items[itemKey(line)]?.id ?? null),
                description_label: line.item?.name ?? items[itemKey(line)]?.label ?? (line.code || null),
                boxes_count: line.packets,
                pieces_per_packet: line.pieces_per_packet,
                boxes_per_carton: line.boxes_per_carton,
                carton_cbm: line.carton_cbm,
                unit_cost: line.unit_cost,
                colors: line.colors.map((color) => {
                    const chosen = colors[colorKey(color.name)];
                    return { color_id: color.color_id ?? chosen?.id ?? null, color_label: chosen?.label ?? color.name, pieces: color.pieces };
                }),
                groups: line.groups.map((group) => ({ lens_power_id: group.lens_power_id, lens_power_label: inHundreds(group.power), packets: group.packets })),
            })),
        );

    return (
        <Modal
            open
            onClose={onClose}
            size="2xl"
            title={label('title')}
            description={
                <span className="inline-flex items-center gap-1.5">
                    <FileSpreadsheet className="size-4" />
                    {name}
                </span>
            }
            footer={
                <>
                    {footerNote && <p className="me-auto self-center text-xs text-muted">{footerNote}</p>}
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" onClick={fill}>
                        {label('fill')}
                    </Button>
                </>
            }
        >
            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="me-auto text-sm text-muted">{label('intro')}</p>
                    {errorCount > 0 && (
                        <Badge tone="danger" icon={<CircleX />}>
                            {label('errors', { count: errorCount })}
                        </Badge>
                    )}
                    {warningCount > 0 && (
                        <Badge tone="warning" icon={<TriangleAlert />}>
                            {label('warnings', { count: warningCount })}
                        </Badge>
                    )}
                    {allIssues.length === 0 && (
                        <Badge tone="success" icon={<CircleCheck />}>
                            {label('all_clear')}
                        </Badge>
                    )}
                </div>

                <Section title={label('reconciliation')} hint={label(hasWrittenTotals ? 'reconciliation_hint' : 'calculated_hint')}>
                    <Reconciliation result={result} hasWrittenTotals={hasWrittenTotals} />
                </Section>

                {result.issues.length > 0 && (
                    <Section title={label('file_issues')}>
                        <IssueList issues={result.issues} />
                    </Section>
                )}

                {unknownItems.length > 0 && (
                    <Section title={label('map_items')} hint={label('map_items_hint')}>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {unknownItems.map(([key, line]) => (
                                <ChoiceRow key={key} name={line.code || label('rows_label', { rows: rowsText(line.rows) })} chosen={Boolean(items[key])}>
                                    <SearchSelect
                                        endpoint="/purchase-invoices/line-options"
                                        value={items[key]?.id ?? null}
                                        initialLabel={items[key]?.label}
                                        invalid={!items[key]}
                                        onChange={(value, option) =>
                                            setItems((current) => {
                                                const next = { ...current };
                                                if (value === null || !option) delete next[key];
                                                else next[key] = { id: Number(value), label: option.label };
                                                return next;
                                            })
                                        }
                                    />
                                </ChoiceRow>
                            ))}
                        </div>
                    </Section>
                )}

                {unknownColors.length > 0 && (
                    <Section title={label('map_colors')} hint={label('map_colors_hint')}>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {unknownColors.map(([key, colorName]) => (
                                <ChoiceRow key={key} name={colorName} chosen={Boolean(colors[key])}>
                                    <SearchSelect
                                        endpoint="/options/colors"
                                        value={colors[key]?.id ?? null}
                                        initialLabel={colors[key]?.label}
                                        invalid={!colors[key]}
                                        onChange={(value, option) =>
                                            setColors((current) => {
                                                const next = { ...current };
                                                if (value === null || !option) delete next[key];
                                                else next[key] = { id: Number(value), label: option.label };
                                                return next;
                                            })
                                        }
                                    />
                                </ChoiceRow>
                            ))}
                        </div>
                    </Section>
                )}

                <Section title={`${label('lines')} (${result.lines.length})`}>
                    <div className="grid gap-3 lg:grid-cols-2">
                        {result.lines.map((line, index) => (
                            <LineReview key={`${line.code}-${index}`} line={line} issues={openIssues(line)} item={items[itemKey(line)]?.label ?? line.item?.name ?? null} colors={colors} />
                        ))}
                    </div>
                </Section>
            </div>
        </Modal>
    );
}

function Section({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
    return (
        <section>
            <h3 className="text-[13.5px] font-semibold">{title}</h3>
            {hint && <p className="mt-0.5 text-xs text-muted">{hint}</p>}
            <div className="mt-2.5">{children}</div>
        </section>
    );
}

/** The file's own totals beside what was read, row by row, each marked as matching or not. */
function Reconciliation({ result, hasWrittenTotals }: { result: ExcelResult; hasWrittenTotals: boolean }) {
    const t = useT();
    const label = (key: string) => t(`admin/purchases.excel.${key}`);
    const { written, read } = result.summary;
    const rows: { key: string; written: number | null; read: number | null; money?: boolean }[] = [
        { key: 'items', written: null, read: read.items },
        { key: 'rows', written: null, read: read.rows },
        { key: 'packets', written: written.packets, read: read.packets },
        { key: 'cartons', written: written.cartons, read: read.cartons },
        { key: 'pieces', written: null, read: read.pieces },
        { key: 'amount', written: written.amount, read: read.amount, money: true },
    ];
    const show = (value: number | null, money?: boolean) => (value === null ? '—' : money ? yuan(value) : number(value));

    return (
        <div className="overflow-hidden rounded-xl border border-line">
            <table className="w-full text-sm">
                <thead className="bg-subtle text-xs text-muted">
                    <tr>
                        <th className="px-3 py-2 text-start font-medium" />
                        {hasWrittenTotals && <th className="px-3 py-2 text-end font-medium">{label('in_file')}</th>}
                        <th className="px-3 py-2 text-end font-medium">{label('read')}</th>
                        {hasWrittenTotals && <th className="w-28 px-3 py-2" />}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {rows.map(({ key, written, read, money }) => {
                        const comparable = written !== null && read !== null;
                        const differs = comparable && Math.abs(written - read) > 0.01;
                        return (
                            <tr key={key} className={cn(differs && 'bg-warning-soft/40')}>
                                <td className="px-3 py-2 font-medium">{label(key)}</td>
                                {hasWrittenTotals && (
                                    <td className="px-3 py-2 text-end text-muted tabular-nums" dir="ltr">{show(written, money)}</td>
                                )}
                                <td className="px-3 py-2 text-end font-semibold tabular-nums" dir="ltr">
                                    {show(read, money)}
                                </td>
                                {hasWrittenTotals && (
                                    <td className="px-3 py-2 text-end">
                                        {comparable && (
                                            <Badge tone={differs ? 'warning' : 'success'} icon={differs ? <TriangleAlert /> : <CircleCheck />}>
                                                {label(differs ? 'differs' : 'matches')}
                                            </Badge>
                                        )}
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function ChoiceRow({ name, chosen, children }: { name: string; chosen: boolean; children: ReactNode }) {
    return (
        <div className={cn('rounded-lg border p-2.5', chosen ? 'border-line' : 'border-warning/50 bg-warning-soft/30')}>
            <p className="mb-1.5 flex items-center gap-1.5 text-[13px] font-semibold" dir="ltr">
                {chosen ? <CircleCheck className="size-3.5 text-success" /> : <TriangleAlert className="size-3.5 text-warning" />}
                {name}
            </p>
            {children}
        </div>
    );
}

/** One line of the file: its item, its numbers, its colors and powers, and what is still wrong in it. */
function LineReview({ line, issues, item, colors }: { line: ExcelLine; issues: ExcelIssue[]; item: string | null; colors: Record<string, Choice> }) {
    const t = useT();
    const label = (key: string, replace?: Record<string, string | number>) => t(`admin/purchases.excel.${key}`, replace);
    const errors = issues.filter((issue) => issue.level === 'error').length;
    const facts: [string, ReactNode][] = [
        [label('packets'), number(line.packets)],
        [label('pieces_per_packet'), number(line.pieces_per_packet)],
        [label('boxes_per_carton'), number(line.boxes_per_carton)],
        [label('cartons'), number(line.cartons)],
        [label('carton_cbm'), number(line.carton_cbm, 6)],
        [label('unit_cost'), yuan(line.unit_cost)],
        [label('amount'), yuan(line.amount)],
    ];

    return (
        <div className={cn('rounded-xl border p-3.5', errors > 0 ? 'border-danger/40' : issues.length > 0 ? 'border-warning/50' : 'border-line')}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="flex items-center gap-2">
                        <span className="font-semibold" dir="ltr">
                            {line.code || '—'}
                        </span>
                        <span className="text-xs text-muted">{label('rows_label', { rows: `⁦${rowsText(line.rows)}⁩` })}</span>
                    </p>
                    <p className={cn('mt-0.5 truncate text-sm', item ? 'text-ink' : 'text-danger')}>{item ?? label('not_chosen')}</p>
                </div>
                {errors > 0 ? (
                    <Badge tone="danger" icon={<CircleX />}>
                        {label('errors', { count: errors })}
                    </Badge>
                ) : issues.length > 0 ? (
                    <Badge tone="warning" icon={<TriangleAlert />}>
                        {label('warnings', { count: issues.length })}
                    </Badge>
                ) : (
                    <Badge tone="success" icon={<CircleCheck />}>
                        {label('no_issues')}
                    </Badge>
                )}
            </div>

            <dl className="mt-3 grid grid-cols-3 gap-x-3 gap-y-2 text-sm">
                {facts.map(([name, value]) => (
                    <div key={name} className="min-w-0">
                        <dt className="truncate text-xs text-muted">{name}</dt>
                        <dd className="font-medium tabular-nums" dir="ltr">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>

            {line.colors.length > 0 && (
                <div className="mt-3">
                    <p className="mb-1 text-xs text-muted">{label('colors')}</p>
                    <div className="flex flex-wrap gap-1.5">
                        {line.colors.map((color, index) => {
                            const chosen = colors[colorKey(color.name)];
                            return (
                                <Badge key={index} tone={color.color_id !== null || chosen ? 'gray' : 'danger'}>
                                    <span dir="ltr">{color.name}</span>
                                    {chosen && <span>→ {chosen.label}</span>}
                                    <span className="tabular-nums">× {color.pieces}</span>
                                </Badge>
                            );
                        })}
                    </div>
                </div>
            )}

            {line.groups.length > 0 && (
                <div className="mt-3">
                    <p className="mb-1 text-xs text-muted">
                        {label('powers')}{' '}
                        <bdi dir="ltr">
                            ({number(line.groups.reduce((sum, group) => sum + group.packets, 0))} / {number(line.packets)})
                        </bdi>
                    </p>
                    <div className="flex flex-wrap gap-1.5" dir="ltr">
                        {line.groups.map((group, index) => (
                            <Badge key={index} tone={group.lens_power_id === null ? 'danger' : 'info'}>
                                <span className="tabular-nums">
                                    {group.power > 0 ? '+' : ''}{inHundreds(group.power)} × {group.packets}
                                </span>
                            </Badge>
                        ))}
                    </div>
                </div>
            )}

            {issues.length > 0 && (
                <div className="mt-3 border-t border-line pt-3">
                    <IssueList issues={issues} />
                </div>
            )}
        </div>
    );
}

function IssueList({ issues }: { issues: ExcelIssue[] }) {
    const t = useT();
    return (
        <ul className="space-y-1.5">
            {issues.map((issue, index) => (
                <li key={index} className={cn('flex items-start gap-2 text-[13px]', issue.level === 'error' ? 'text-danger' : 'text-warning')}>
                    {issue.level === 'error' ? <CircleX className="mt-0.5 size-3.5 shrink-0" /> : issue.type === 'two_prices_averaged' ? <Info className="mt-0.5 size-3.5 shrink-0" /> : <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />}
                    <span className="min-w-0">
                        {issue.message}
                        {issue.row !== null && <span className="ms-1.5 text-xs text-muted">({t('admin/purchases.excel.row', { row: issue.row })})</span>}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/**
 * How a new invoice's lines are entered, offered before the first line: from the supplier's Excel file (the faster way,
 * read and checked first) or typed by hand. Either way the other stays one click away.
 */
export function EntryChoice({ onFill, onManual }: { onFill: (lines: ImportedLine[]) => void; onManual: () => void }) {
    const t = useT();
    const label = (key: string) => t(`admin/purchases.excel.entry.${key}`);

    return (
        <div className="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <div className="mb-5 text-center">
                <h3 className="text-base font-semibold">{label('title')}</h3>
                <p className="mt-1 text-sm text-muted">{label('subtitle')}</p>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <ExcelImport
                    onFill={onFill}
                    trigger={(choose, reading) => (
                        <ChoiceCard
                            highlighted
                            icon={<FileSpreadsheet className="size-5" />}
                            title={label('excel_title')}
                            badge={label('excel_badge')}
                            body={label('excel_body')}
                            points={[label('excel_point_1'), label('excel_point_2'), label('excel_point_3')]}
                            action={
                                <div className="flex flex-wrap gap-2">
                                    <ExcelTemplateDownload endpoint="/purchase-invoices/excel/template" translationKey="admin/purchases.excel" />
                                    <Button variant="primary" icon={<FileSpreadsheet className="size-4" />} loading={reading} onClick={choose}>
                                        {label('excel_action')}
                                    </Button>
                                </div>
                            }
                        />
                    )}
                />
                <ChoiceCard
                    icon={<PenLine className="size-5" />}
                    title={label('manual_title')}
                    body={label('manual_body')}
                    points={[label('manual_point_1'), label('manual_point_2')]}
                    action={
                        <Button className="w-full" icon={<PenLine className="size-4" />} onClick={onManual}>
                            {label('manual_action')}
                        </Button>
                    }
                />
            </div>
        </div>
    );
}

function ChoiceCard({ icon, title, badge, body, points, action, highlighted = false }: { icon: ReactNode; title: string; badge?: string; body: string; points: string[]; action: ReactNode; highlighted?: boolean }) {
    return (
        <div className={cn('flex flex-col rounded-xl border p-4 sm:p-5', highlighted ? 'border-accent/40 bg-accent-soft/40' : 'border-line')}>
            <div className="flex items-center gap-3">
                <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', highlighted ? 'bg-accent text-white' : 'bg-subtle text-muted')}>{icon}</span>
                <h4 className="font-semibold">{title}</h4>
                {badge && (
                    <Badge tone="accent" icon={<Zap />} className="ms-auto">
                        {badge}
                    </Badge>
                )}
            </div>
            <p className="mt-3 text-sm text-muted">{body}</p>
            <ul className="mt-3 mb-5 space-y-1.5">
                {points.map((point) => (
                    <li key={point} className="flex items-start gap-2 text-[13px]">
                        <CircleCheck className={cn('mt-0.5 size-3.5 shrink-0', highlighted ? 'text-accent' : 'text-success')} />
                        {point}
                    </li>
                ))}
            </ul>
            <div className="mt-auto">{action}</div>
        </div>
    );
}
