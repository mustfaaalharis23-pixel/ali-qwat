import { useState } from 'react';
import { Plus } from 'lucide-react';
import { api } from '../lib/api';
import { number } from '../lib/format';
import { useCreateAnother, useForm, useSave } from '../lib/form';
import { useT } from '../lib/i18n';
import { CreateAnotherButton } from './CreateAnotherButton';
import { Badge, Button, Field, Input, Modal } from './ui';
import { DataTable } from './DataTable';
import { PageHeader } from './PageHeader';
import { useRecordActions, type Abilities } from './RecordActions';

interface Category {
    id: number;
    name: string;
    items_count: number;
    invoices_count: number;
    is_used: boolean;
    deleted_at: string | null;
    can: Abilities;
}

function CategoryForm({ category, endpoint, onClose }: { category: Category | null; endpoint: string; onClose: () => void }) {
    const t = useT();
    const another = useCreateAnother();
    const form = useForm({ name: category?.name ?? '' });
    const save = useSave((body: typeof form.values) => (category ? api.put(`${endpoint}/${category.id}`, body) : api.post(endpoint, body)), {
        invalidate: ['list', 'record', 'options'],
        success: t('admin.actions.saved'),
        onSuccess: () => another.after(onClose, () => form.reset()),
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
                    {!category && <CreateAnotherButton another={another} disabled={save.isPending} onClick={() => save.mutate(form.values)} />}
                    <Button
                        variant="primary"
                        loading={save.isPending}
                        onClick={() => {
                            another.ask(false);
                            save.mutate(form.values);
                        }}
                    >
                        {t('admin.actions.save')}
                    </Button>
                </>
            }
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    another.ask(false);
                    save.mutate(form.values);
                }}
            >
                <Field label={t('filament/resources/category.fields.name')} error={form.errors.name} required>
                    {(id) => <Input id={id} autoFocus value={form.values.name} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                </Field>
            </form>
        </Modal>
    );
}

export function CategoriesPage({ section }: { section: 'items' | 'purchases' }) {
    const t = useT();
    const [editing, setEditing] = useState<Category | null | undefined>(undefined);
    const endpoint = section === 'items' ? '/item-categories' : '/invoice-categories';
    const parent = section === 'items'
        ? { label: t('filament/resources/item.plural_label'), to: '/items' }
        : { label: t('filament/resources/purchase_invoice.plural_label'), to: '/purchase-invoices' };
    const actions = useRecordActions<Category>(endpoint, { label: (category) => category.name });

    return (
        <>
            <PageHeader
                title={t('filament/resources/category.plural_label')}
                breadcrumbs={[parent, { label: t('filament/resources/category.plural_label') }]}
            />
            <DataTable<Category>
                endpoint={endpoint}
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
                        header: t('filament/resources/category.fields.name'),
                        sortable: true,
                        cell: (category) => (
                            <span className="flex items-center gap-2 font-medium">
                                {category.name}
                                {category.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'items_count', header: t('filament/resources/category.fields.items_count'), sortable: true, align: 'end', cell: (category) => number(category.items_count) },
                    { key: 'invoices_count', header: t('filament/resources/category.fields.invoices_count'), sortable: true, align: 'end', cell: (category) => number(category.invoices_count) },
                ]}
                rowActions={(category) => actions.items(category, { edit: () => setEditing(category) })}
            />
            {editing !== undefined && <CategoryForm category={editing} endpoint={endpoint} onClose={() => setEditing(undefined)} />}
            {actions.dialogs}
        </>
    );
}
