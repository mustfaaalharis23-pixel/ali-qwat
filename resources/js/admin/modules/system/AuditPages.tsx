import { useParams, useSearchParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeftRight, ClipboardList } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, dateTime } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, DescriptionList, Empty, Input, PageSpinner, toneOf, type Option } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import type { Audit } from './AuditsPanel';
import { Section } from './shared';

interface AuditFilters {
    events: Option[];
    subject_types: Option[];
    causers: Option[];
}

/** The audits of the whole system, newest first: who changed which record, and when. */
export function AuditsPage() {
    const t = useT();
    const [params, setParams] = useSearchParams();
    const { data: filters } = useQuery({ queryKey: ['options', 'audits-filters'], queryFn: () => api.get<AuditFilters>('/audits/filters'), staleTime: 60_000 });
    const from = params.get('created_from') ?? '';
    const until = params.get('created_until') ?? '';
    const setDate = (key: string, value: string) =>
        setParams((existing) => {
            const next = new URLSearchParams(existing);
            if (value) next.set(key, value);
            else next.delete(key);
            next.delete('page');
            return next;
        }, { replace: true });

    return (
        <>
            <PageHeader title={t('filament/resources/audit.plural_label')} />
            <DataTable<Audit>
                endpoint="/audits"
                searchable={false}
                query={{ created_from: from || undefined, created_until: until || undefined }}
                defaultSort={{ column: 'id', direction: 'desc' }}
                filters={[
                    { key: 'event', label: t('filament/resources/audit.fields.event'), options: filters?.events ?? [] },
                    { key: 'subject_type', label: t('filament/resources/audit.fields.subject_type'), options: filters?.subject_types ?? [] },
                    { key: 'causer', label: t('filament/resources/audit.fields.causer'), options: filters?.causers ?? [] },
                ]}
                toolbar={
                    <div className="flex flex-wrap items-center gap-2 text-[13px] text-muted">
                        <label className="flex items-center gap-1.5">
                            {t('filament/resources/audit.fields.created_from')}
                            <Input type="date" value={from} onChange={(event) => setDate('created_from', event.target.value)} className={cn('h-9 w-auto', from && 'border-accent/50')} />
                        </label>
                        <label className="flex items-center gap-1.5">
                            {t('filament/resources/audit.fields.created_until')}
                            <Input type="date" value={until} onChange={(event) => setDate('created_until', event.target.value)} className={cn('h-9 w-auto', until && 'border-accent/50')} />
                        </label>
                    </div>
                }
                rowHref={(audit) => `/audits/${audit.id}`}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (audit) => <span className="text-muted tabular-nums">{audit.id}</span> },
                    { key: 'event', header: t('filament/resources/audit.fields.event'), cell: (audit) => <Badge tone={toneOf(audit.event.color)}>{audit.event.label}</Badge> },
                    { key: 'subject_type', header: t('filament/resources/audit.fields.subject_type'), cell: (audit) => audit.subject_type_label },
                    { key: 'subject_id', header: t('filament/resources/audit.fields.subject_id'), cell: (audit) => <span className="tabular-nums">{audit.subject_id ?? '—'}</span> },
                    { key: 'causer', header: t('filament/resources/audit.fields.causer'), cell: (audit) => audit.causer?.name ?? <span className="text-muted">{t('filament/resources/audit.system')}</span> },
                    { key: 'created_at', header: t('filament/resources/audit.fields.created_at'), sortable: true, cell: (audit) => <span className="tabular-nums whitespace-nowrap">{dateTime(audit.created_at)}</span> },
                ]}
            />
        </>
    );
}

/** One audit: its details, then each changed field with the value before and the value after. */
export function AuditPage() {
    const t = useT();
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', 'audit', id], queryFn: () => api.get<{ data: Audit }>(`/audits/${id}`) });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const audit = data.data;

    return (
        <>
            <PageHeader
                title={`${t('filament/resources/audit.label')} #${audit.id}`}
                breadcrumbs={[{ label: t('filament/resources/audit.plural_label'), to: '/audits' }, { label: `#${audit.id}` }]}
                badge={<Badge tone={toneOf(audit.event.color)}>{audit.event.label}</Badge>}
            />
            <div className="space-y-5">
                <Section title={t('filament/resources/audit.sections.details')} icon={<ClipboardList className="size-4" />}>
                    <DescriptionList
                        items={[
                            { label: t('filament/resources/audit.fields.event'), value: <Badge tone={toneOf(audit.event.color)}>{audit.event.label}</Badge> },
                            { label: t('filament/resources/audit.fields.subject_type'), value: audit.subject_type_label },
                            { label: t('filament/resources/audit.fields.subject_id'), value: audit.subject_id },
                            { label: t('filament/resources/audit.fields.causer'), value: audit.causer?.name ?? t('filament/resources/audit.system') },
                            { label: t('filament/resources/audit.fields.created_at'), value: <span className="tabular-nums">{dateTime(audit.created_at)}</span> },
                        ]}
                    />
                </Section>
                <section className="card overflow-hidden">
                    <div className="flex items-center gap-2 border-b border-line px-5 py-3.5 text-[15px] font-semibold">
                        <ArrowLeftRight className="size-4 text-muted" />
                        {t('filament/resources/audit.sections.changes')}
                    </div>
                    {(audit.changes ?? []).length === 0 ? (
                        <Empty title={t('admin/system.audits.no_changes')} />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                                        <th className="px-4 py-2.5 text-start font-medium">{t('filament/resources/audit.fields.attribute')}</th>
                                        <th className="px-4 py-2.5 text-start font-medium">{t('filament/resources/audit.fields.old')}</th>
                                        <th className="px-4 py-2.5 text-start font-medium">{t('filament/resources/audit.fields.new')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(audit.changes ?? []).map((change, index) => (
                                        <tr key={index} className="border-b border-line align-top last:border-0">
                                            <td className="px-4 py-2.5 font-medium whitespace-nowrap">{change.attribute}</td>
                                            <td className="px-4 py-2.5 break-all text-muted">{change.old ?? '—'}</td>
                                            <td className={cn('px-4 py-2.5 break-all', change.old !== change.new && 'text-ink')}>{change.new ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
