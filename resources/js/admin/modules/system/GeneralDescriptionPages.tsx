import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Plus, Tag } from 'lucide-react';
import { api } from '../../lib/api';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, DescriptionList, Field, Input, Modal, PageSpinner, Select, Toggle } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { useRecordActions, type Abilities } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { BoolIcon, BulkActions, EnumBadge, Notice, RecordHeaderActions, RecordTabs, Section, Segmented, useInvoiceTypes, useIsSuper, useRowMenu, useTransferTypes, type EnumValue } from './shared';
import type { SpecificDescription } from './SpecificDescriptionPages';

export interface GeneralDescription {
    id: number;
    name: string;
    type: EnumValue;
    is_purchases_description: boolean;
    is_capital_movement: boolean;
    specific_descriptions_count?: number;
    deleted_at: string | null;
    can: Abilities;
}

const PATH = '/general-descriptions';

export function GeneralDescriptionsPage() {
    const t = useT();
    const types = useInvoiceTypes();
    const menu = useRowMenu<GeneralDescription>(PATH, PATH, (description) => description.name);

    return (
        <>
            <PageHeader title={t('filament/resources/general_description.plural_label')} />
            <DataTable<GeneralDescription>
                endpoint={PATH}
                trashed
                selectable
                bulkActions={(selected, clear) => <BulkActions endpoint={PATH} selected={selected} clear={clear} />}
                filters={[{ key: 'type', label: t('filament/resources/general_description.fields.type'), options: types }]}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <ButtonLink to={`${PATH}/create`} variant="primary" size="sm" icon={<Plus className="size-4" />}>
                            {t('admin.actions.new')}
                        </ButtonLink>
                    )
                }
                defaultSort={{ column: 'id', direction: 'asc' }}
                rowHref={(description) => `${PATH}/${description.id}`}
                rowActions={(description) => menu.items(description)}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (description) => <span className="text-muted tabular-nums">{description.id}</span> },
                    {
                        key: 'name',
                        header: t('filament/resources/general_description.fields.name'),
                        sortable: true,
                        cell: (description) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                {description.name}
                                {description.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'type', header: t('filament/resources/general_description.fields.type'), cell: (description) => <EnumBadge value={description.type} /> },
                    { key: 'is_purchases_description', header: t('filament/resources/general_description.fields.is_purchases_description'), align: 'center', cell: (description) => <span className="inline-flex"><BoolIcon value={description.is_purchases_description} /></span> },
                    { key: 'is_capital_movement', header: t('filament/resources/general_description.fields.is_capital_movement'), align: 'center', cell: (description) => <span className="inline-flex"><BoolIcon value={description.is_capital_movement} /></span> },
                ]}
            />
            {menu.dialogs}
        </>
    );
}

export function GeneralDescriptionFormPage() {
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: GeneralDescription }>(`${PATH}/${id}`), enabled: Boolean(id) });
    if (id && isLoading) return <PageSpinner />;
    if (id && (error || !data)) return <QueryError error={error} />;
    return <GeneralDescriptionForm description={data?.data ?? null} />;
}

function GeneralDescriptionForm({ description }: { description: GeneralDescription | null }) {
    const t = useT();
    const navigate = useNavigate();
    const types = useInvoiceTypes();
    const form = useForm({ name: description?.name ?? '', type: description?.type.value ?? '', is_capital_movement: description?.is_capital_movement ?? false });
    const save = useSave((body: typeof form.values) => (description ? api.put<{ data: GeneralDescription }>(`${PATH}/${description.id}`, body) : api.post<{ data: GeneralDescription }>(PATH, body)), {
        invalidate: ['list', 'record', 'options'],
        success: t('admin.actions.saved'),
        onSuccess: (result) => navigate(`${PATH}/${result.data.id}`),
        onError: form.fail,
    });
    const title = description ? description.name : `${t('admin.actions.create')} ${t('filament/resources/general_description.label')}`;

    return (
        <form onSubmit={(event) => { event.preventDefault(); save.mutate(form.values); }}>
            <PageHeader
                title={title}
                breadcrumbs={[{ label: t('filament/resources/general_description.plural_label'), to: PATH }, { label: description ? t('admin.actions.edit') : t('admin.actions.create') }]}
                actions={description && <RecordHeaderActions record={description} endpoint={PATH} path={PATH} label={description.name} edit={false} />}
            />
            <Section title={t('filament/resources/general_description.sections.details')} icon={<Tag className="size-4" />}>
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <Field label={t('filament/resources/general_description.fields.name')} error={form.errors.name} required>
                        {(fieldId) => <Input id={fieldId} autoFocus value={form.values.name} maxLength={255} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                    </Field>
                    <Field label={t('filament/resources/general_description.fields.type')} error={form.errors.type} required>
                        {() => <div><Segmented options={types} value={form.values.type} onChange={(value) => form.set('type', value)} /></div>}
                    </Field>
                    {!description?.is_purchases_description && (
                        <div className="md:col-span-2">
                            <Field label={t('filament/resources/general_description.fields.is_capital_movement')} hint={t('filament/resources/general_description.hints.is_capital_movement')} error={form.errors.is_capital_movement}>
                                {() => <div><Toggle checked={form.values.is_capital_movement} onChange={(checked) => form.set('is_capital_movement', checked)} /></div>}
                            </Field>
                        </div>
                    )}
                </div>
            </Section>
            <div className="mt-5 flex justify-end gap-2">
                <ButtonLink to={description ? `${PATH}/${description.id}` : PATH}>{t('admin.actions.cancel')}</ButtonLink>
                <Button type="submit" variant="primary" loading={save.isPending}>
                    {description ? t('admin.actions.save_changes') : t('admin.actions.create')}
                </Button>
            </div>
        </form>
    );
}

export function GeneralDescriptionPage() {
    const t = useT();
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: GeneralDescription }>(`${PATH}/${id}`) });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const description = data.data;

    return (
        <>
            <PageHeader
                title={description.name}
                breadcrumbs={[{ label: t('filament/resources/general_description.plural_label'), to: PATH }, { label: description.name }]}
                badge={description.deleted_at ? <Badge tone="danger">{t('admin.actions.deleted')}</Badge> : <EnumBadge value={description.type} />}
                actions={<RecordHeaderActions record={description} endpoint={PATH} path={PATH} label={description.name} />}
            />
            <RecordTabs
                subjectType="general_description"
                subjectId={description.id}
                tabs={[
                    {
                        value: 'details',
                        label: t('admin/system.tabs.details'),
                        content: (
                            <div className="space-y-5">
                                <Section title={t('filament/resources/general_description.sections.details')} icon={<Tag className="size-4" />}>
                                    <DescriptionList
                                        items={[
                                            { label: t('filament/resources/general_description.fields.name'), value: description.name },
                                            { label: t('filament/resources/general_description.fields.type'), value: <EnumBadge value={description.type} /> },
                                            { label: t('filament/resources/general_description.fields.is_purchases_description'), value: <BoolIcon value={description.is_purchases_description} /> },
                                            { label: t('filament/resources/general_description.fields.is_capital_movement'), value: <BoolIcon value={description.is_capital_movement} />, hidden: description.is_purchases_description },
                                        ]}
                                    />
                                </Section>
                                <SpecificDescriptionsOf description={description} />
                            </div>
                        ),
                    },
                ]}
            />
        </>
    );
}

/**
 * The specific descriptions under one general description (Filament's relation manager): created, edited, and deleted here,
 * except under the purchases description, whose descriptions are created with the items and are read only.
 */
function SpecificDescriptionsOf({ description }: { description: GeneralDescription }) {
    const t = useT();
    const [editing, setEditing] = useState<SpecificDescription | null | undefined>(undefined);
    const readOnly = description.is_purchases_description;
    const actions = useRecordActions<SpecificDescription>('/specific-descriptions', { label: (item) => item.name });

    return (
        <section className="space-y-3">
            <h2 className="text-[15px] font-semibold">{t('filament/resources/specific_description.plural_label')}</h2>
            {readOnly && <Notice tone="info">{t('admin/system.general_descriptions.purchases_read_only')}</Notice>}
            <DataTable<SpecificDescription>
                endpoint="/specific-descriptions"
                stateKey="specific"
                trashed
                query={{ general_description_id: description.id }}
                defaultSort={{ column: 'id', direction: 'asc' }}
                selectable={!readOnly}
                bulkActions={(selected, clear) => <BulkActions endpoint="/specific-descriptions" selected={selected} clear={clear} />}
                toolbar={(response) =>
                    !readOnly &&
                    !description.deleted_at &&
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <Button variant="primary" size="sm" icon={<Plus className="size-4" />} onClick={() => setEditing(null)}>
                            {t('admin.actions.new')}
                        </Button>
                    )
                }
                rowHref={(item) => `/specific-descriptions/${item.id}`}
                rowActions={readOnly ? undefined : (item) => actions.items(item, { edit: () => setEditing(item) })}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (item) => <span className="text-muted tabular-nums">{item.id}</span> },
                    {
                        key: 'name',
                        header: t('filament/resources/specific_description.fields.name'),
                        sortable: true,
                        cell: (item) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                {item.name}
                                {item.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'transfer_type', header: t('filament/resources/specific_description.fields.transfer_type'), cell: (item) => <EnumBadge value={item.transfer_type} /> },
                ]}
            />
            {editing !== undefined && (
                <SpecificDescriptionModal
                    generalDescriptionId={description.id}
                    description={editing}
                    onClose={() => setEditing(undefined)}
                />
            )}
            {actions.dialogs}
        </section>
    );
}

function SpecificDescriptionModal({ generalDescriptionId, description, onClose }: { generalDescriptionId: number; description: SpecificDescription | null; onClose: () => void }) {
    const t = useT();
    const isSuper = useIsSuper();
    const transferTypes = useTransferTypes();
    const form = useForm({ name: description?.name ?? '', transfer_type: description?.transfer_type?.value ?? '' });
    const save = useSave(
        (body: typeof form.values) => {
            const payload = { general_description_id: generalDescriptionId, name: body.name, ...(isSuper ? { transfer_type: body.transfer_type || null } : {}) };
            return description ? api.put(`/specific-descriptions/${description.id}`, payload) : api.post('/specific-descriptions', payload);
        },
        {
            invalidate: ['list', 'record', 'options'],
            success: t('admin.actions.saved'),
            onSuccess: onClose,
            onError: form.fail,
        },
    );

    return (
        <Modal
            open
            onClose={onClose}
            size="sm"
            title={description ? t('admin.actions.edit') : `${t('admin.actions.create')} ${t('filament/resources/specific_description.label')}`}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={save.isPending} onClick={() => save.mutate(form.values)}>
                        {t('admin.actions.save')}
                    </Button>
                </>
            }
        >
            <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); save.mutate(form.values); }}>
                <Field label={t('filament/resources/specific_description.fields.name')} error={form.errors.name ?? form.errors.general_description_id} required>
                    {(fieldId) => <Input id={fieldId} autoFocus value={form.values.name} maxLength={255} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                </Field>
                {isSuper && (
                    <Field label={t('filament/resources/specific_description.fields.transfer_type')} error={form.errors.transfer_type}>
                        {(fieldId) => (
                            <Select
                                id={fieldId}
                                value={form.values.transfer_type}
                                onChange={(event) => form.set('transfer_type', event.target.value)}
                                placeholder="—"
                                options={transferTypes}
                            />
                        )}
                    </Field>
                )}
            </form>
        </Modal>
    );
}
