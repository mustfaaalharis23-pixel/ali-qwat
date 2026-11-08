import { useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Plus, Tag } from 'lucide-react';
import { api } from '../../lib/api';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, DescriptionList, Field, Input, PageSpinner, Select } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import type { Abilities } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { BulkActions, EnumBadge, Notice, RecordHeaderActions, RecordTabs, Section, Segmented, useInvoiceTypes, useIsSuper, useRowMenu, useTransferTypes, type EnumValue } from './shared';

export interface SpecificDescription {
    id: number;
    name: string;
    general_description_id: number;
    general_description: { id: number; name: string; type: EnumValue; is_purchases_description: boolean } | null;
    item: { id: number; name: string } | null;
    is_item_description: boolean;
    transfer_type: EnumValue | null;
    deleted_at: string | null;
    can: Abilities;
}

const PATH = '/specific-descriptions';

/** The specific descriptions. The items' own descriptions are hidden unless the filter asks for them, and are never changed here. */
export function SpecificDescriptionsPage() {
    const t = useT();
    const menu = useRowMenu<SpecificDescription>(PATH, PATH, (description) => description.name);
    const { data: generalDescriptions } = useQuery({
        queryKey: ['options', 'general-descriptions-all'],
        queryFn: () => api.get<{ data: { value: number; label: string }[] }>('/options/general-descriptions'),
        staleTime: 60_000,
    });

    return (
        <>
            <PageHeader title={t('filament/resources/specific_description.plural_label')} />
            <DataTable<SpecificDescription>
                endpoint={PATH}
                trashed
                selectable
                bulkActions={(selected, clear) => <BulkActions endpoint={PATH} selected={selected} clear={clear} />}
                filters={[
                    { key: 'general_description_id', label: t('filament/resources/specific_description.fields.general_description'), options: generalDescriptions?.data ?? [] },
                    {
                        key: 'item_descriptions',
                        label: t('admin/system.item_filter.without'),
                        options: [
                            { value: 'with', label: t('admin/system.item_filter.with') },
                            { value: 'only', label: t('admin/system.item_filter.only') },
                        ],
                    },
                ]}
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
                        header: t('filament/resources/specific_description.fields.name'),
                        sortable: true,
                        cell: (description) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                {description.name}
                                {description.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'general_description', header: t('filament/resources/specific_description.fields.general_description'), cell: (description) => description.general_description?.name ?? '—' },
                    { key: 'item', header: t('filament/resources/specific_description.fields.item'), cell: (description) => description.item?.name ?? <span className="text-faint">—</span> },
                    { key: 'transfer_type', header: t('filament/resources/specific_description.fields.transfer_type'), cell: (description) => <EnumBadge value={description.transfer_type} /> },
                ]}
            />
            {menu.dialogs}
        </>
    );
}

export function SpecificDescriptionFormPage() {
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: SpecificDescription }>(`${PATH}/${id}`), enabled: Boolean(id) });
    if (id && isLoading) return <PageSpinner />;
    if (id && (error || !data)) return <QueryError error={error} />;
    return <SpecificDescriptionForm description={data?.data ?? null} />;
}

/**
 * The type (input or output) is chosen first and narrows the general descriptions to that type; it is not saved.
 * An item's description is shown read only.
 */
function SpecificDescriptionForm({ description }: { description: SpecificDescription | null }) {
    const t = useT();
    const navigate = useNavigate();
    const isSuper = useIsSuper();
    const types = useInvoiceTypes();
    const transferTypes = useTransferTypes();
    const readOnly = description?.is_item_description === true;
    const form = useForm({
        type: description?.general_description?.type.value ?? 'output',
        general_description_id: (description?.general_description_id ?? null) as number | null,
        name: description?.name ?? '',
        transfer_type: description?.transfer_type?.value ?? '',
    });
    const save = useSave(
        (body: typeof form.values) => {
            const payload = { general_description_id: body.general_description_id, name: body.name, ...(isSuper ? { transfer_type: body.transfer_type || null } : {}) };
            return description ? api.put<{ data: SpecificDescription }>(`${PATH}/${description.id}`, payload) : api.post<{ data: SpecificDescription }>(PATH, payload);
        },
        { invalidate: ['list', 'record', 'options'], success: t('admin.actions.saved'), onSuccess: (result) => navigate(`${PATH}/${result.data.id}`), onError: form.fail },
    );

    return (
        <form onSubmit={(event) => { event.preventDefault(); if (!readOnly) save.mutate(form.values); }}>
            <PageHeader
                title={description ? description.name : `${t('admin.actions.create')} ${t('filament/resources/specific_description.label')}`}
                breadcrumbs={[{ label: t('filament/resources/specific_description.plural_label'), to: PATH }, { label: description ? t('admin.actions.edit') : t('admin.actions.create') }]}
                actions={description && <RecordHeaderActions record={description} endpoint={PATH} path={PATH} label={description.name} edit={false} />}
            />
            <div className="space-y-5">
                {readOnly && <Notice tone="info">{t('admin/system.specific_descriptions.item_read_only')}</Notice>}
                <Section title={t('filament/resources/specific_description.sections.details')} icon={<Tag className="size-4" />}>
                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <Field label={t('filament/resources/specific_description.fields.type')} required>
                            {() => (
                                <div>
                                    <Segmented
                                        options={types}
                                        value={form.values.type}
                                        disabled={readOnly}
                                        onChange={(value) => {
                                            form.set('type', value);
                                            form.set('general_description_id', null);
                                        }}
                                    />
                                </div>
                            )}
                        </Field>
                        <Field label={t('filament/resources/specific_description.fields.general_description')} error={form.errors.general_description_id} required>
                            {(fieldId) => (
                                <SearchSelect
                                    id={fieldId}
                                    endpoint="/system-options/general-descriptions"
                                    query={{ type: form.values.type }}
                                    value={form.values.general_description_id}
                                    initialLabel={description?.general_description?.name}
                                    onChange={(value) => form.set('general_description_id', value === null ? null : Number(value))}
                                    disabled={readOnly || !form.values.type}
                                    invalid={Boolean(form.errors.general_description_id)}
                                />
                            )}
                        </Field>
                        <Field label={t('filament/resources/specific_description.fields.name')} error={form.errors.name} required>
                            {(fieldId) => <Input id={fieldId} value={form.values.name} maxLength={255} disabled={readOnly} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                        </Field>
                        {isSuper && (
                            <Field label={t('filament/resources/specific_description.fields.transfer_type')} error={form.errors.transfer_type}>
                                {(fieldId) => <Select id={fieldId} value={form.values.transfer_type} disabled={readOnly} onChange={(event) => form.set('transfer_type', event.target.value)} placeholder="—" options={transferTypes} />}
                            </Field>
                        )}
                    </div>
                </Section>
                <div className="flex justify-end gap-2">
                    <ButtonLink to={description ? `${PATH}/${description.id}` : PATH}>{t('admin.actions.cancel')}</ButtonLink>
                    {!readOnly && (
                        <Button type="submit" variant="primary" loading={save.isPending}>
                            {description ? t('admin.actions.save_changes') : t('admin.actions.create')}
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}

export function SpecificDescriptionPage() {
    const t = useT();
    const isSuper = useIsSuper();
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: SpecificDescription }>(`${PATH}/${id}`) });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const description = data.data;

    return (
        <>
            <PageHeader
                title={description.name}
                breadcrumbs={[{ label: t('filament/resources/specific_description.plural_label'), to: PATH }, { label: description.name }]}
                badge={description.deleted_at ? <Badge tone="danger">{t('admin.actions.deleted')}</Badge> : undefined}
                actions={<RecordHeaderActions record={description} endpoint={PATH} path={PATH} label={description.name} />}
            />
            <RecordTabs
                subjectType="specific_description"
                subjectId={description.id}
                tabs={[
                    {
                        value: 'details',
                        label: t('admin/system.tabs.details'),
                        content: (
                            <div className="space-y-5">
                                {description.is_item_description && <Notice tone="info">{t('admin/system.specific_descriptions.item_read_only')}</Notice>}
                                <Section title={t('filament/resources/specific_description.sections.details')} icon={<Tag className="size-4" />}>
                                    <DescriptionList
                                        items={[
                                            { label: t('filament/resources/specific_description.fields.name'), value: description.name },
                                            { label: t('filament/resources/specific_description.fields.type'), value: <EnumBadge value={description.general_description?.type} /> },
                                            {
                                                label: t('filament/resources/specific_description.fields.general_description'),
                                                value: description.general_description ? (
                                                    <ButtonLink to={`/general-descriptions/${description.general_description.id}`} variant="ghost" size="sm" className="-ms-2.5">
                                                        {description.general_description.name}
                                                    </ButtonLink>
                                                ) : '—',
                                            },
                                            { label: t('filament/resources/specific_description.fields.item'), value: description.item?.name, hidden: !description.item },
                                            { label: t('filament/resources/specific_description.fields.transfer_type'), value: <EnumBadge value={description.transfer_type} />, hidden: !isSuper },
                                        ]}
                                    />
                                </Section>
                            </div>
                        ),
                    },
                ]}
            />
        </>
    );
}
