import { useQuery } from '@tanstack/react-query';
import { api, type Paginated } from '../../lib/api';
import { dateTime } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, toneOf } from '../../components/ui';
import { DataTable } from '../../components/DataTable';

export interface Audit {
    id: number;
    event: { value: string; label: string; color: string };
    subject_type: string | null;
    subject_type_label: string;
    subject_id: number | null;
    causer: { id: number; name: string | null } | null;
    created_at: string;
    changes?: { attribute: string; old: string | null; new: string | null }[];
}

/**
 * Whether the signed-in user may read the audits of a record, so a record page shows its audits tab only then.
 * subjectType is the model's short name (invoice, general_description) or its class.
 */
export function useCanViewAudits(subjectType: string, subjectId: number | string | undefined) {
    const { data, isError } = useQuery({
        queryKey: ['list', '/audits', 'probe', subjectType, subjectId],
        queryFn: () => api.get<Paginated<Audit>>('/audits', { subject_type: subjectType, subject_id: subjectId, per_page: 1 }),
        enabled: subjectId !== undefined,
        retry: false,
        staleTime: 60_000,
    });
    return Boolean(data) && !isError;
}

/**
 * The audits of one record, newest first, as a tab of the record's page (Filament's audits relation manager).
 * Any module can show it: <AuditsPanel subjectType="invoice" subjectId={invoice.id} />.
 */
export function AuditsPanel({ subjectType, subjectId }: { subjectType: string; subjectId: number }) {
    const t = useT();
    return (
        <DataTable<Audit>
            endpoint="/audits"
            stateKey="audits"
            searchable={false}
            query={{ subject_type: subjectType, subject_id: subjectId }}
            defaultSort={{ column: 'id', direction: 'desc' }}
            rowHref={(audit) => `/audits/${audit.id}`}
            columns={[
                { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (audit) => <span className="text-muted tabular-nums">{audit.id}</span> },
                { key: 'event', header: t('filament/resources/audit.fields.event'), cell: (audit) => <Badge tone={toneOf(audit.event.color)}>{audit.event.label}</Badge> },
                { key: 'causer', header: t('filament/resources/audit.fields.causer'), cell: (audit) => audit.causer?.name ?? <span className="text-muted">{t('filament/resources/audit.system')}</span> },
                { key: 'created_at', header: t('filament/resources/audit.fields.created_at'), sortable: true, cell: (audit) => <span className="tabular-nums">{dateTime(audit.created_at)}</span> },
            ]}
        />
    );
}

/**
 * The audits of one record as a titled section at the end of a record page that has no tabs, shown only to users who may read them.
 */
export function AuditsSection({ subjectType, subjectId, className }: { subjectType: string; subjectId: number; className?: string }) {
    const t = useT();
    const canView = useCanViewAudits(subjectType, subjectId);
    if (!canView) return null;
    return (
        <section className={className ?? 'mt-6'}>
            <h2 className="mb-3 text-[15px] font-semibold">{t('filament/resources/audit.plural_label')}</h2>
            <AuditsPanel subjectType={subjectType} subjectId={subjectId} />
        </section>
    );
}
