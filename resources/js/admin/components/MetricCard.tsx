import { useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Banknote, Hash, Info } from 'lucide-react';
import { api, type Query } from '../lib/api';
import { cn } from '../lib/format';
import { useT } from '../lib/i18n';
import { Modal, Skeleton } from './ui';

export interface MetricPayload {
    key?: string;
    label: string;
    value: { number: string; currency: string | null; raw?: number };
    range: string;
    ranges: { value: string; label: string }[];
    explanation: string;
    example: string;
}

/** The info button of a card: its explanation and a simple example, in a dialog. */
export function InfoButton({ title, explanation, example }: { title: ReactNode; explanation?: string; example?: string }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    if (!explanation && !example) return null;
    return (
        <>
            <button type="button" onClick={() => setOpen(true)} className="rounded-md p-1 text-faint transition-colors hover:bg-hover hover:text-ink" aria-label={t('admin.actions.info')}>
                <Info className="size-3.75" />
            </button>
            <Modal open={open} onClose={() => setOpen(false)} title={title} size="md">
                <div className="space-y-4 text-[13.5px] leading-6.5">
                    {explanation && <p className="whitespace-pre-line text-ink/90">{explanation}</p>}
                    {example && (
                        <div className="rounded-xl border border-line bg-subtle p-4">
                            <p className="mb-1.5 text-[11.5px] font-semibold tracking-wide text-muted">{t('admin.metrics.example')}</p>
                            <p className="whitespace-pre-line">{example}</p>
                        </div>
                    )}
                </div>
            </Modal>
        </>
    );
}

/** A number card as the server describes it, with its range list when it offers more than one. */
export function MetricView({ metric, onRange, loading, className, tone, icon }: { metric?: MetricPayload; onRange?: (range: string) => void; loading?: boolean; className?: string; tone?: 'danger' | 'success'; icon?: ReactNode }) {
    const isMoney = Boolean(metric?.value.currency);
    const raw = metric?.value.raw;
    const negative = typeof raw === 'number' && raw < 0;

    return (
        <div className={cn('card group relative flex min-h-[124px] flex-col justify-between gap-4 overflow-hidden p-5 transition-shadow hover:shadow-[var(--shadow-raised)]', className)}>
            <div className="flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span className={cn('flex size-7 shrink-0 items-center justify-center rounded-lg ring-1 [&_svg]:size-3.75', isMoney ? 'bg-accent-soft text-accent ring-accent/10' : 'bg-subtle text-muted ring-line')}>
                        {icon ?? (isMoney ? <Banknote /> : <Hash />)}
                    </span>
                    <p className="truncate text-[13px] font-medium text-muted">{metric?.label ?? <Skeleton className="h-4 w-28" />}</p>
                </div>
                <div className="-me-1 -mt-1 flex shrink-0 items-center gap-0.5">
                    {metric && metric.ranges.length > 1 && onRange && (
                        <select
                            value={metric.range}
                            onChange={(event) => onRange(event.target.value)}
                            className="h-7 cursor-pointer rounded-md border-0 bg-subtle ps-2 text-[11.5px] font-medium text-muted outline-none hover:bg-hover hover:text-ink"
                        >
                            {metric.ranges.map((range) => (
                                <option key={range.value} value={range.value}>
                                    {range.label}
                                </option>
                            ))}
                        </select>
                    )}
                    {metric && <InfoButton title={metric.label} explanation={metric.explanation} example={metric.example} />}
                </div>
            </div>
            {metric && !loading ? (
                <p className={cn('flex items-baseline gap-1.5', tone === 'danger' || negative ? 'text-danger' : tone === 'success' ? 'text-success' : 'text-ink')}>
                    <span className="num text-[28px] leading-none font-semibold tracking-[-0.02em]">{metric.value.number}</span>
                    {metric.value.currency && <span className="text-[13px] font-medium text-faint">{metric.value.currency}</span>}
                </p>
            ) : (
                <Skeleton className="h-7 w-36" />
            )}
        </div>
    );
}

/** A card that reads itself from the API and asks again when its range changes. */
export function RemoteMetric({ endpoint, query, className, icon }: { endpoint: string; query?: Query; className?: string; icon?: ReactNode }) {
    const [range, setRange] = useState<string | undefined>();
    const { data, isFetching } = useQuery({
        queryKey: ['metric', endpoint, range, query],
        queryFn: () => api.get<MetricPayload>(endpoint, { ...query, range }),
        placeholderData: (previous) => previous,
    });
    return <MetricView metric={data} onRange={setRange} loading={isFetching && !data} className={className} icon={icon} />;
}
