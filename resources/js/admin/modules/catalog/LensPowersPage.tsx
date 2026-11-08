import { useState } from 'react';
import { Plus } from 'lucide-react';
import { api } from '../../lib/api';
import { number } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, Field, Input, Modal } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { useRecordActions, type Abilities } from '../../components/RecordActions';

interface LensPower {
    id: number;
    value: string;
    invoice_item_groups_count: number;
    is_used: boolean;
    deleted_at: string | null;
    can: Abilities;
}

function LensPowerForm({ power, onClose }: { power: LensPower | null; onClose: () => void }) {
    const t = useT();
    const form = useForm({ value: power?.value ?? '' });
    const save = useSave((body: typeof form.values) => (power ? api.put(`/lens-powers/${power.id}`, body) : api.post('/lens-powers', body)), {
        invalidate: ['list', 'options'],
        success: t('admin.actions.saved'),
        onSuccess: onClose,
        onError: form.fail,
    });

    return (
        <Modal
            open
            onClose={onClose}
            size="sm"
            title={power ? t('admin.actions.edit') : t('admin.actions.create')}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={save.isPending} onClick={() => save.mutate(form.values)}>
                        {t('admin.actions.save')}
                    </Button>
                </>
            }
        >
            <form onSubmit={(event) => { event.preventDefault(); save.mutate(form.values); }}>
                <Field label={t('filament/resources/lens_power.fields.value')} error={form.errors.value} required>
                    {(id) => <Input id={id} autoFocus type="number" step="0.01" dir="ltr" value={form.values.value} onChange={(event) => form.set('value', event.target.value)} invalid={Boolean(form.errors.value)} />}
                </Field>
            </form>
        </Modal>
    );
}

/** The lens powers, from the lowest to the highest. A power on purchase lines cannot be deleted, so its delete action is hidden. */
export function LensPowersPage() {
    const t = useT();
    const [editing, setEditing] = useState<LensPower | null | undefined>(undefined);
    const actions = useRecordActions<LensPower>('/lens-powers', { label: (power) => power.value });

    return (
        <>
            <PageHeader title={t('filament/resources/lens_power.plural_label')} />
            <DataTable<LensPower>
                endpoint="/lens-powers"
                trashed
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <Button variant="primary" size="sm" icon={<Plus className="size-4" />} onClick={() => setEditing(null)}>
                            {t('admin.actions.new')}
                        </Button>
                    )
                }
                defaultSort={{ column: 'value', direction: 'asc' }}
                columns={[
                    { key: 'id', header: '#', cell: (power) => <span className="text-muted tabular-nums">{power.id}</span>, sortable: true, className: 'w-16' },
                    {
                        key: 'value',
                        header: t('filament/resources/lens_power.fields.value'),
                        sortable: true,
                        cell: (power) => (
                            <span className="inline-flex items-center gap-2 font-medium tabular-nums">
                                {power.value}
                                {power.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'invoice_item_groups_count', header: t('filament/resources/lens_power.fields.invoice_items_count'), sortable: true, align: 'end', cell: (power) => number(power.invoice_item_groups_count) },
                ]}
                rowActions={(power) => actions.items({ ...power, can: { ...power.can, delete: power.can.delete && !power.is_used } }, { edit: () => setEditing(power) })}
            />
            {editing !== undefined && <LensPowerForm power={editing} onClose={() => setEditing(undefined)} />}
            {actions.dialogs}
        </>
    );
}
