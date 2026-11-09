import { useRef, useState, type ReactNode } from 'react';
import { Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { FileText, Paperclip, Upload, X } from 'lucide-react';
import { api, ApiError } from '../../lib/api';
import { cn, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Button, buttonClass, Card, CardHeader, Skeleton, toast, toneOf, type Tone } from '../../components/ui';
import type { Abilities } from '../../components/RecordActions';

/** An enum as the server sends it: its value, its label, and its Filament color. */
export interface EnumValue {
    value: string;
    label: string;
    color: string | null;
}

export interface SplitFact {
    text: string;
    hint: string;
    tone: string;
}

export interface PurchaseInvoiceRow {
    id: number;
    name: string;
    split: SplitFact | null;
    supplier_id: number | null;
    supplier: { id: number; name: string } | null;
    invoice_category_id: number | null;
    category: { id: number; name: string } | null;
    /** can_view: whether the user may open the containers, so the container is a link only for them (as the panel hid it). */
    container: { id: number; name: string; can_view?: boolean } | null;
    date: string | null;
    amount: number;
    paid_amount: number;
    remaining: number;
    status: EnumValue | null;
    stage: EnumValue | null;
    awaited_confirmation: EnumValue | null;
    user: string | null;
    deleted_at: string | null;
    print_url: string;
    label_url: string;
    can: Abilities & { advance_stage: boolean; next_stage: EnumValue | null; confirm: boolean; revert_confirmation: boolean };
}

/** Money of the suppliers is in Chinese yuan. */
export const yuan = (value: number | string | null | undefined) => (value === null || value === undefined || value === '' ? '—' : `${number(value)} ¥`);
export const dollars = (value: number | string | null | undefined, decimals = 2) => (value === null || value === undefined || value === '' ? '—' : `${number(value, decimals)} $`);
export const dinars = (value: number | string | null | undefined) => (value === null || value === undefined || value === '' ? '—' : `${number(value, 0)} IQD`);

/** A number typed in a field, or zero. */
export const toNumber = (value: unknown): number => {
    const parsed = typeof value === 'number' ? value : Number(String(value ?? '').replace(/,/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
};

export function EnumBadge({ value, tone }: { value: EnumValue | null | undefined; tone?: Tone }) {
    if (!value) return <span className="text-faint">—</span>;
    return <Badge tone={tone ?? toneOf(value.color)}>{value.label}</Badge>;
}

export function SplitBadge({ split }: { split: SplitFact | null }) {
    if (!split) return null;
    return (
        <span title={split.hint}>
            <Badge tone={toneOf(split.tone)}>{split.text}</Badge>
        </span>
    );
}

/** A small chip of a fact under a field, with its explanation on hover. */
export interface Fact {
    text: ReactNode;
    hint?: string;
    tone: Tone;
    icon?: ReactNode;
}

export function Facts({ facts, className }: { facts: Fact[]; className?: string }) {
    if (facts.length === 0) return null;
    return (
        <div className={cn('flex flex-wrap gap-1.5', className)}>
            {facts.map((fact, index) => (
                <span key={index} title={fact.hint}>
                    <Badge tone={fact.tone} icon={fact.icon}>
                        {fact.text}
                    </Badge>
                </span>
            ))}
        </div>
    );
}

const PIE_COLORS = ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'];
const OTHERS_COLOR = '#9ca3af';

interface PieData {
    heading: string;
    parts: { name: string; value: number; is_others: boolean }[];
}

/** The pie of a number for each supplier, the largest five in colors and the rest as one gray part, with a legend beside it. */
export function SupplierPie({ endpoint, className }: { endpoint: string; className?: string }) {
    const t = useT();
    const { data } = useQuery({ queryKey: ['metric', endpoint], queryFn: () => api.get<PieData>(endpoint) });
    const total = data?.parts.reduce((sum, part) => sum + part.value, 0) ?? 0;
    const colorOf = (index: number, isOthers: boolean) => (isOthers ? OTHERS_COLOR : PIE_COLORS[index % PIE_COLORS.length]);

    return (
        <Card className={className}>
            <CardHeader title={data?.heading ?? <Skeleton className="h-4 w-40" />} />
            <div className="flex flex-col items-center gap-4 p-4 sm:flex-row">
                {!data ? (
                    <Skeleton className="size-44 rounded-full" />
                ) : data.parts.length === 0 ? (
                    <p className="w-full py-10 text-center text-sm text-muted">{t('admin.table.empty')}</p>
                ) : (
                    <>
                        <div className="size-36 shrink-0" dir="ltr">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie data={data.parts} dataKey="value" nameKey="name" innerRadius={46} outerRadius={68} paddingAngle={2} cornerRadius={3} stroke="none" isAnimationActive={false}>
                                        {data.parts.map((part, index) => (
                                            <Cell key={index} fill={colorOf(index, part.is_others)} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(value) => yuan(Number(value))} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                        <ul className="w-full max-w-md min-w-0 flex-1 space-y-2.5 text-[13px]">
                            {data.parts.map((part, index) => (
                                <li key={index} className="flex items-center justify-between gap-3">
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="size-2.5 shrink-0 rounded-full" style={{ background: colorOf(index, part.is_others) }} />
                                        <span className="truncate">{part.name}</span>
                                    </span>
                                    <span className="shrink-0 text-muted tabular-nums">
                                        {yuan(part.value)}
                                        <span className="ms-2 text-xs text-faint">{total > 0 ? `${number((part.value / total) * 100, 1)}%` : ''}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </Card>
    );
}

/**
 * A file field: the file is uploaded on its own first (to the endpoint, which answers { path, url }), then the form keeps its path,
 * like the panel's file fields. Several files when multiple is set.
 */
export function FileField({
    endpoint,
    extra = {},
    accept,
    files,
    onChange,
    multiple,
    images,
}: {
    endpoint: string;
    extra?: Record<string, string>;
    accept: string;
    files: { path: string; url: string }[];
    onChange: (files: { path: string; url: string }[]) => void;
    multiple?: boolean;
    images?: boolean;
}) {
    const t = useT();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const upload = async (list: FileList | null) => {
        if (!list || list.length === 0) return;
        setUploading(true);
        const added: { path: string; url: string }[] = [];
        try {
            for (const file of Array.from(list)) {
                const body = new FormData();
                body.append('file', file);
                Object.entries(extra).forEach(([key, value]) => body.append(key, value));
                added.push(await api.post<{ path: string; url: string }>(endpoint, body));
            }
        } catch (error) {
            toast.error(error instanceof ApiError ? (error.first() ?? error.message) : String(error));
        } finally {
            setUploading(false);
            if (input.current) input.current.value = '';
        }
        onChange(multiple ? [...files, ...added] : added.slice(-1));
    };

    return (
        <div className="space-y-2.5">
            {files.length > 0 && (
                <div className={cn(images ? 'flex flex-wrap gap-2' : 'space-y-1.5')}>
                    {files.map((file) =>
                        images ? (
                            <div key={file.path} className="group relative">
                                <a href={file.url} target="_blank" rel="noreferrer">
                                    <img src={file.url} alt="" className="size-20 rounded-lg border border-line object-cover" />
                                </a>
                                <button
                                    type="button"
                                    onClick={() => onChange(files.filter((other) => other.path !== file.path))}
                                    className="absolute -end-1.5 -top-1.5 rounded-full border border-line bg-surface p-0.5 text-muted shadow-sm hover:text-danger"
                                    aria-label={t('admin.actions.remove')}
                                >
                                    <X className="size-3.5" />
                                </button>
                            </div>
                        ) : (
                            <div key={file.path} className="flex items-center justify-between gap-2 rounded-lg border border-line px-3 py-2 text-sm">
                                <a href={file.url} target="_blank" rel="noreferrer" className="flex min-w-0 items-center gap-2 text-accent hover:underline">
                                    <FileText className="size-4 shrink-0" />
                                    <span className="truncate" dir="ltr">
                                        {file.path.split('/').pop()}
                                    </span>
                                </a>
                                <button type="button" onClick={() => onChange(files.filter((other) => other.path !== file.path))} className="text-faint hover:text-danger" aria-label={t('admin.actions.remove')}>
                                    <X className="size-4" />
                                </button>
                            </div>
                        ),
                    )}
                </div>
            )}
            {(multiple || files.length === 0) && (
                <>
                    <input ref={input} type="file" accept={accept} multiple={multiple} className="hidden" onChange={(event) => upload(event.target.files)} />
                    <Button size="sm" loading={uploading} icon={images ? <Upload className="size-4" /> : <Paperclip className="size-4" />} onClick={() => input.current?.click()}>
                        {t('admin/purchases.upload.choose')}
                    </Button>
                </>
            )}
        </div>
    );
}

/** The API's errors under a key, keyed as Laravel keys them: the errors of "items.0.boxes_count" are under "items.0". */
export function errorsUnder(errors: Record<string, string>, prefix: string): string[] {
    return Object.entries(errors)
        .filter(([key]) => key === prefix || key.startsWith(`${prefix}.`))
        .map(([, message]) => message);
}

/**
 * A button that opens a page of the application. The shared ButtonLink sends paths that start with /purchase-invoices
 * to the server (for the print links), so the purchase invoice pages are opened with this one instead.
 */
export function RouteButton({ to, variant = 'secondary', size = 'md', icon, children }: { to: string; variant?: 'primary' | 'secondary'; size?: 'sm' | 'md'; icon?: ReactNode; children: ReactNode }) {
    return (
        <Link to={to} className={buttonClass(variant, size)}>
            {icon}
            {children}
        </Link>
    );
}
