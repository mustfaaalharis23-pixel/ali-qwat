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

interface InvoiceCategory {
    id: number;
    name: string;
    invoices_count: number;
    is_used: boolean;
    deleted_at: string | null;
    can: Abilities;
}

function CategoryForm({ category, onClose }: { category: InvoiceCategory | null; onClose: () => void }) {
    const t = useT();
    const form = useForm({ name: category?.name ?? '' });
    const save = useSave((body: typeof form.values) => (category ? api.put(`/invoice-categories/${category.id}`, body) : api.post('/invoice-categories', body)), {
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
            title={category ? t('admin.actions.edit') : t('admin.actions.create')}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={save.isPending} onClick={() => save.mutate(form.values)}>
                        {t('admin.actions.save')}
                    </Button>
                </>
            }
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    save.mutate(form.values);
                }}
            >
                <Field label={t('filament/resources/invoice_category.fields.name')} error={form.errors.name} required>
                    {(id) => <Input id={id} autoFocus value={form.values.name} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                </Field>
            </form>
        </Modal>
    );
}

/** The categories of the purchase invoices: a list with an add and edit dialog. A used category offers no delete. */
export function InvoiceCategoriesPage() {
    const t = useT();
    const [editing, setEditing] = useState<InvoiceCategory | null | undefined>(undefined);
    const actions = useRecordActions<InvoiceCategory>('/invoice-categories', { label: (category) => category.name });

    return (
        <>
            <PageHeader
                title={t('filament/resources/invoice_category.plural_label')}
                breadcrumbs={[{ label: t('filament/resources/purchase_invoice.plural_label'), to: '/purchase-invoices' }, { label: t('filament/resources/invoice_category.plural_label') }]}
            />
            <DataTable<InvoiceCategory>
                endpoint="/invoice-categories"
                trashed
                defaultSort={{ column: 'id', direction: 'desc' }}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <Button variant="primary" size="sm" icon={<Plus className="size-4" />} onClick={() => setEditing(null)}>
                            {t('admin.actions.new')}
                        </Button>
                    )
                }
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (category) => <span className="text-muted tabular-nums">{category.id}</span> },
                    {
                        key: 'name',
                        header: t('filament/resources/invoice_category.fields.name'),
                        sortable: true,
                        cell: (category) => (
                            <span className="flex items-center gap-2 font-medium">
                                {category.name}
                                {category.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'invoices_count', header: t('filament/resources/invoice_category.fields.invoices_count'), sortable: true, align: 'end', cell: (category) => number(category.invoices_count) },
                ]}
                rowActions={(category) => actions.items(category, { edit: () => setEditing(category) })}
            />
            {editing !== undefined && <CategoryForm category={editing} onClose={() => setEditing(undefined)} />}
            {actions.dialogs}
        </>
    );
}
