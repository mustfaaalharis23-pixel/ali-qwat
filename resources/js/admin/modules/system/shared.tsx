import { useState, type ReactNode } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { useQueryClient } from '@tanstack/react-query';
import { Check, Ellipsis, Eye, Pencil, RotateCcw, Trash2, TriangleAlert, X } from 'lucide-react';
import { api } from '../../lib/api';
import { useT } from '../../lib/i18n';
import { useSession } from '../../lib/session';
import { cn } from '../../lib/format';
import { Badge, Button, ButtonLink, ConfirmDialog, Menu, Tabs, toast, toneOf, type MenuItem } from '../../components/ui';
import { useRecordActions, type Abilities } from '../../components/RecordActions';
import { AuditsPanel, useCanViewAudits } from './AuditsPanel';

/** An enum value as the server sends it: its value, its label in the current language, and its badge color. */
export interface EnumValue {
    value: string;
    label: string;
    color: string | null;
}

export function EnumBadge({ value }: { value: EnumValue | null | undefined }) {
    if (!value) return <span className="text-faint">—</span>;
    return <Badge tone={toneOf(value.color)}>{value.label}</Badge>;
}

/** A yes or no column, as a check or a cross. */
export function BoolIcon({ value }: { value: boolean }) {
    const t = useT();
    return value ? (
        <Check className="size-4 text-success" aria-label={t('admin.actions.yes')} />
    ) : (
        <X className="size-4 text-faint" aria-label={t('admin.actions.no')} />
    );
}

/** A warning box with a title and its text, such as the money transfer notice. */
export function Notice({ title, children, tone = 'warning' }: { title?: ReactNode; children?: ReactNode; tone?: 'warning' | 'info' }) {
    return (
        <div className={cn('flex gap-3 rounded-xl border p-4 text-sm', tone === 'warning' ? 'border-warning/40 bg-warning-soft' : 'border-info/30 bg-info-soft')}>
            <TriangleAlert className={cn('mt-0.5 size-4 shrink-0', tone === 'warning' ? 'text-[oklch(0.5_0.12_60)]' : 'text-info')} />
            <div className="min-w-0">
                {title && <p className="font-semibold">{title}</p>}
                {children && <p className={cn('leading-6', Boolean(title) && 'mt-1 text-muted')}>{children}</p>}
            </div>
        </div>
    );
}

/** A titled block of a form or a details page. */
export function Section({ title, icon, actions, children, className }: { title: ReactNode; icon?: ReactNode; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={cn('card', className)}>
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                <h2 className="flex items-center gap-2 text-[15px] font-semibold">
                    {icon && <span className="text-muted">{icon}</span>}
                    {title}
                </h2>
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

/** A choice between a few options shown side by side, like Filament's inline toggle buttons. */
export function Segmented({ options, value, onChange, disabled }: { options: { value: string; label: string }[]; value: string; onChange: (value: string) => void; disabled?: boolean }) {
    return (
        <div className="inline-flex rounded-lg border border-line bg-subtle p-0.5" role="radiogroup">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="radio"
                    aria-checked={option.value === value}
                    disabled={disabled}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'rounded-md px-3.5 py-1.5 text-[13px] font-medium transition-colors disabled:opacity-60',
                        option.value === value ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

export function useIsSuper(): boolean {
    return useSession().data?.user?.is_super === true;
}

/** The input and output choices of a general description or an invoice. */
export function useInvoiceTypes() {
    const t = useT();
    return [
        { value: 'output', label: t('filament/resources/general_description.types.output') },
        { value: 'input', label: t('filament/resources/general_description.types.input') },
    ];
}

/** The transfer types of a specific description: the sales box its money is withdrawn from. */
export function useTransferTypes() {
    const t = useT();
    return [
        { value: 'cash', label: t('orders.payment_types.cash') },
        { value: 'postpaid', label: t('orders.payment_types.postpaid') },
    ];
}

/** The menu of a list row: view, edit, then delete, restore, and delete forever with their confirmations. */
export function useRowMenu<T extends { id: number; can?: Abilities }>(endpoint: string, path: string, label: (record: T) => string) {
    const t = useT();
    const navigate = useNavigate();
    const actions = useRecordActions<T>(endpoint, { label });
    const items = (record: T, options: { edit?: boolean } = {}): MenuItem[] => [
        { label: t('admin.actions.view'), icon: <Eye className="size-4" />, onSelect: () => navigate(`${path}/${record.id}`) },
        ...actions.items(record, options.edit === false ? {} : { edit: () => navigate(`${path}/${record.id}/edit`) }),
    ];
    return { items, dialogs: actions.dialogs };
}

/**
 * The actions at the top of a record's page: its edit button, and a menu with delete, restore, and delete forever.
 * Deleting goes back to the list.
 */
export function RecordHeaderActions<T extends { id: number; can?: Abilities }>({ record, endpoint, path, label, edit = true }: { record: T; endpoint: string; path: string; label: string; edit?: boolean }) {
    const t = useT();
    const navigate = useNavigate();
    const actions = useRecordActions<T>(endpoint, { label: () => label, onDeleted: () => navigate(path) });
    const items = actions.items(record);
    const visible = items.filter((item) => !item.hidden);

    return (
        <>
            {edit && record.can?.update && (
                <ButtonLink to={`${path}/${record.id}/edit`} variant="primary" icon={<Pencil className="size-4" />}>
                    {t('admin.actions.edit')}
                </ButtonLink>
            )}
            {visible.length > 0 && (
                <Menu
                    items={items}
                    trigger={(toggle) => (
                        <Button onClick={toggle} aria-label={t('admin.actions.more')} icon={<Ellipsis className="size-4" />} />
                    )}
                />
            )}
            {actions.dialogs}
        </>
    );
}

/**
 * The bulk actions of a list: delete, restore, and delete forever the selected records, one request per record,
 * so each record keeps its own permission. Records that cannot be changed are counted and reported.
 */
export function BulkActions({ endpoint, selected, clear, restore = true, forceDelete = true }: { endpoint: string; selected: (string | number)[]; clear: () => void; restore?: boolean; forceDelete?: boolean }) {
    const t = useT();
    const client = useQueryClient();
    const [pending, setPending] = useState<'delete' | 'force' | null>(null);
    const [running, setRunning] = useState(false);

    const run = async (kind: 'delete' | 'restore' | 'force') => {
        setRunning(true);
        const results = await Promise.allSettled(
            selected.map((id) => (kind === 'delete' ? api.delete(`${endpoint}/${id}`) : kind === 'force' ? api.delete(`${endpoint}/${id}/force`) : api.post(`${endpoint}/${id}/restore`))),
        );
        setRunning(false);
        setPending(null);
        const failed = results.filter((result) => result.status === 'rejected').length;
        const done = results.length - failed;
        if (done > 0) toast.success(t('admin/system.bulk.done', { count: done }));
        if (failed > 0) toast.error(t('admin/system.bulk.failed', { count: failed }));
        client.invalidateQueries({ predicate: (query) => query.queryKey.some((part) => typeof part === 'string' && (part.startsWith('list') || part.startsWith('record') || part.startsWith('options'))) });
        clear();
    };

    return (
        <>
            <span className="text-[13px] text-muted">{t('admin.table.selected', { count: selected.length })}</span>
            <Button size="sm" variant="secondary" icon={<Trash2 className="size-3.5" />} onClick={() => setPending('delete')}>
                {t('admin.actions.delete')}
            </Button>
            {restore && (
                <Button size="sm" variant="secondary" icon={<RotateCcw className="size-3.5" />} loading={running && pending === null} onClick={() => run('restore')}>
                    {t('admin.actions.restore')}
                </Button>
            )}
            {forceDelete && (
                <Button size="sm" variant="secondary" icon={<Trash2 className="size-3.5 text-danger" />} onClick={() => setPending('force')}>
                    {t('admin.actions.force_delete')}
                </Button>
            )}
            <ConfirmDialog
                open={pending !== null}
                onClose={() => setPending(null)}
                onConfirm={() => pending && run(pending)}
                loading={running}
                title={t('admin.confirm.delete')}
                description={pending === 'force' ? t('admin.confirm.force_delete_description') : t('admin.confirm.delete_description')}
                confirmLabel={pending === 'force' ? t('admin.actions.force_delete') : t('admin.actions.delete')}
            />
        </>
    );
}

/**
 * The tabs of a record's page: its details, any other lists of it, and its audits when the user may read them.
 * The chosen tab lives in the address (?tab=).
 */
export function RecordTabs({ subjectType, subjectId, tabs }: { subjectType: string; subjectId: number; tabs: { value: string; label: ReactNode; content: ReactNode }[] }) {
    const t = useT();
    const [params, setParams] = useSearchParams();
    const canViewAudits = useCanViewAudits(subjectType, subjectId);
    const all = canViewAudits ? [...tabs, { value: 'audits', label: t('filament/resources/audit.plural_label'), content: <AuditsPanel subjectType={subjectType} subjectId={subjectId} /> }] : tabs;
    const current = all.find((tab) => tab.value === params.get('tab')) ?? all[0];

    return (
        <div className="space-y-4">
            {all.length > 1 && (
                <Tabs
                    tabs={all.map((tab) => ({ value: tab.value, label: tab.label }))}
                    value={current.value}
                    onChange={(value) => setParams((existing) => { const next = new URLSearchParams(existing); next.set('tab', value); return next; }, { replace: true })}
                />
            )}
            <div>{current.content}</div>
        </div>
    );
}
