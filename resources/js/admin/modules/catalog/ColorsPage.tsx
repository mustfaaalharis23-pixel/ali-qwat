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

interface Color {
    id: number;
    name: string;
    swatch: string;
    invoice_item_colors_count: number;
    is_used: boolean;
    deleted_at: string | null;
    can: Abilities;
}

function ColorForm({ color, onClose }: { color: Color | null; onClose: () => void }) {
    const t = useT();
    const form = useForm({ name: color?.name ?? '' });
    const save = useSave((body: typeof form.values) => (color ? api.put(`/colors/${color.id}`, body) : api.post('/colors', body)), {
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
            title={color ? t('admin.actions.edit') : t('admin.actions.create')}
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
                <Field label={t('filament/resources/color.fields.name')} error={form.errors.name} required>
                    {(id) => <Input id={id} autoFocus value={form.values.name} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                </Field>
            </form>
        </Modal>
    );
}

export function ColorsPage() {
    const t = useT();
    const [editing, setEditing] = useState<Color | null | undefined>(undefined);
    const actions = useRecordActions<Color>('/colors', { label: (color) => color.name });

    return (
        <>
            <PageHeader title={t('filament/resources/color.plural_label')} />
            <DataTable<Color>
                endpoint="/colors"
                trashed
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <Button variant="primary" size="sm" icon={<Plus className="size-4" />} onClick={() => setEditing(null)}>
                            {t('admin.actions.new')}
                        </Button>
                    )
                }
                defaultSort={{ column: 'name', direction: 'asc' }}
                columns={[
                    { key: 'id', header: '#', cell: (color) => <span className="text-muted tabular-nums">{color.id}</span>, sortable: true, className: 'w-16' },
                    {
                        key: 'name',
                        header: t('filament/resources/color.fields.name'),
                        sortable: true,
                        cell: (color) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                <span className="size-3.5 rounded-full ring-1 ring-line" style={{ background: color.swatch }} />
                                {color.name}
                                {color.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'invoice_item_colors_count', header: t('filament/resources/color.fields.invoice_item_colors_count'), sortable: true, align: 'end', cell: (color) => number(color.invoice_item_colors_count) },
                ]}
                rowActions={(color) => actions.items(color, { edit: () => setEditing(color) })}
            />
            {editing !== undefined && <ColorForm color={editing} onClose={() => setEditing(undefined)} />}
            {actions.dialogs}
        </>
    );
}
