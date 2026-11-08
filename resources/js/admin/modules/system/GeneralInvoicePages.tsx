import { useRef, useState } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { Banknote, FileText, ImagePlus, ListOrdered, Lock, Paperclip, Plus, Trash2, X } from 'lucide-react';
import { api, ApiError } from '../../lib/api';
import { cn, date, money, number } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, DescriptionList, Empty, Field, Input, PageSpinner, Skeleton, Spinner, Textarea, toast } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect, type SearchOption } from '../../components/SearchSelect';
import type { Abilities } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { BulkActions, EnumBadge, Notice, RecordHeaderActions, RecordTabs, Section, Segmented, useInvoiceTypes, useIsSuper, useRowMenu, type EnumValue } from './shared';

interface Line {
    id: number;
    specific_description_id: number;
    specific_description: string | null;
    price: number;
}

interface GeneralInvoice {
    id: number;
    name: string;
    type: EnumValue;
    date: string;
    amount: number;
    general_description: { id: number; name: string } | null;
    cashier: { id: number; name: string } | null;
    user: { id: number; name: string } | null;
    is_money_transfer: boolean;
    items?: Line[];
    images?: { path: string; url: string }[];
    notes?: string | null;
    created_at: string;
    deleted_at: string | null;
    can: Abilities;
}

const PATH = '/general-invoices';

/** The pie of where the general expenses went, above the list. */
function ExpensesChart() {
    const t = useT();
    const { data, isLoading } = useQuery({
        queryKey: ['list', 'general-expenses-chart'],
        queryFn: () => api.get<{ heading: string; slices: { name: string; value: number; color: string }[] }>(`${PATH}/expenses-chart`),
    });

    return (
        <section className="card mb-5 p-4.5">
            <h2 className="mb-2 text-[13px] font-medium text-muted">{data?.heading ?? t('filament/resources/general_invoice.widgets.expenses_chart.heading')}</h2>
            {isLoading ? (
                <Skeleton className="h-[220px] w-full" />
            ) : !data || data.slices.length === 0 ? (
                <p className="py-10 text-center text-sm text-muted">{t('admin/system.general_invoices.chart_empty')}</p>
            ) : (
                <div className="h-[260px]" dir="ltr">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie data={data.slices} dataKey="value" nameKey="name" innerRadius="45%" outerRadius="80%" paddingAngle={1} stroke="var(--color-surface)" strokeWidth={2} isAnimationActive={false}>
                                {data.slices.map((slice) => (
                                    <Cell key={slice.name} fill={slice.color} />
                                ))}
                            </Pie>
                            <Tooltip
                                formatter={(value) => money(Number(value))}
                                contentStyle={{ background: 'var(--color-surface)', border: '1px solid var(--color-line)', borderRadius: 10, fontSize: 13 }}
                            />
                            <Legend layout="vertical" align="right" verticalAlign="middle" iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 13, color: 'var(--color-muted)' }} />
                        </PieChart>
                    </ResponsiveContainer>
                </div>
            )}
        </section>
    );
}

export function GeneralInvoicesPage() {
    const t = useT();
    const isSuper = useIsSuper();
    const types = useInvoiceTypes();
    const menu = useRowMenu<GeneralInvoice>(PATH, PATH, (invoice) => invoice.name);
    const { data: descriptions } = useQuery({
        queryKey: ['options', 'general-invoice-descriptions'],
        queryFn: () => api.get<{ data: SearchOption[] }>('/system-options/general-descriptions'),
        staleTime: 60_000,
    });

    return (
        <>
            <PageHeader title={t('filament/resources/general_invoice.plural_label')} />
            <ExpensesChart />
            <DataTable<GeneralInvoice>
                endpoint={PATH}
                trashed
                selectable
                bulkActions={(selected, clear) => <BulkActions endpoint={PATH} selected={selected} clear={clear} restore={isSuper} forceDelete={isSuper} />}
                filters={[
                    { key: 'type', label: t('filament/resources/invoice.fields.type'), options: types },
                    { key: 'general_description_id', label: t('filament/resources/invoice.fields.general_description_id'), options: (descriptions?.data ?? []).map((option) => ({ value: option.value, label: option.label })) },
                ]}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <ButtonLink to={`${PATH}/create`} variant="primary" size="sm" icon={<Plus className="size-4" />}>
                            {t('admin.actions.new')}
                        </ButtonLink>
                    )
                }
                defaultSort={{ column: 'date', direction: 'desc' }}
                rowHref={(invoice) => `${PATH}/${invoice.id}`}
                rowActions={(invoice) => menu.items(invoice)}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (invoice) => <span className="text-muted tabular-nums">{invoice.id}</span> },
                    {
                        key: 'name',
                        header: t('filament/resources/invoice.fields.name'),
                        cell: (invoice) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                {invoice.name}
                                {invoice.is_money_transfer && <Lock className="size-3.5 text-faint" aria-label={t('admin/system.general_invoices.read_only')} />}
                                {invoice.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'type', header: t('filament/resources/invoice.fields.type'), cell: (invoice) => <EnumBadge value={invoice.type} /> },
                    { key: 'general_description', header: t('filament/resources/invoice.fields.general_description_id'), cell: (invoice) => invoice.general_description?.name ?? '—' },
                    { key: 'date', header: t('filament/resources/invoice.fields.date'), sortable: true, cell: (invoice) => <span className="tabular-nums whitespace-nowrap">{date(invoice.date)}</span> },
                    { key: 'amount', header: t('filament/resources/invoice.fields.amount'), sortable: true, align: 'end', cell: (invoice) => <span className="font-medium tabular-nums whitespace-nowrap">{number(invoice.amount)}</span> },
                    { key: 'cashier', header: t('filament/resources/invoice.fields.cashier_id'), cell: (invoice) => invoice.cashier?.name ?? <span className="text-faint">—</span> },
                ]}
            />
            {menu.dialogs}
        </>
    );
}

export function GeneralInvoiceFormPage() {
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: GeneralInvoice }>(`${PATH}/${id}`), enabled: Boolean(id) });
    if (id && isLoading) return <PageSpinner />;
    if (id && (error || !data)) return <QueryError error={error} />;
    // A money transfer invoice is read only, so its edit page shows its view page instead.
    if (data?.data && (data.data.is_money_transfer || !data.data.can.update)) return <Navigate to={`${PATH}/${data.data.id}`} replace />;
    return <GeneralInvoiceForm invoice={data?.data ?? null} />;
}

interface LineDraft {
    key: number;
    id: number | null;
    specific_description_id: number | null;
    label: string | null;
    price: string;
}

let nextLineKey = 1;
const emptyLine = (): LineDraft => ({ key: nextLineKey++, id: null, specific_description_id: null, label: null, price: '' });
const today = () => {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};
const priceOf = (price: string) => {
    const parsed = Number(price.replace(/,/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
};

/**
 * The general invoice form. The general description is chosen after the type and narrows the lines' descriptions.
 * A general description that holds transfer descriptions shows the money transfer notice and cannot be saved.
 * The amount is shown read only: the model computes it as the sum of the lines.
 */
function GeneralInvoiceForm({ invoice }: { invoice: GeneralInvoice | null }) {
    const t = useT();
    const navigate = useNavigate();
    const types = useInvoiceTypes();
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(0);
    const [holdsTransfer, setHoldsTransfer] = useState(false);
    const form = useForm({
        name: invoice?.name ?? '',
        date: invoice?.date ?? today(),
        type: invoice?.type.value ?? 'output',
        general_description_id: (invoice?.general_description?.id ?? null) as number | null,
        general_description_label: invoice?.general_description?.name ?? null,
        items: (invoice?.items ?? []).length > 0
            ? (invoice?.items ?? []).map((line): LineDraft => ({ key: nextLineKey++, id: line.id, specific_description_id: line.specific_description_id, label: line.specific_description, price: String(line.price) }))
            : [emptyLine()],
        images: invoice?.images ?? [],
        notes: invoice?.notes ?? '',
    });
    const values = form.values;
    const total = values.items.reduce((sum, line) => sum + priceOf(line.price), 0);
    const lineError = (index: number, field: string) => form.errors[`items.${index}.${field}`];

    const setLine = (key: number, changes: Partial<LineDraft>) => form.set('items', values.items.map((line) => (line.key === key ? { ...line, ...changes } : line)));

    const save = useSave(
        () => {
            const payload = {
                name: values.name,
                date: values.date,
                type: values.type,
                general_description_id: values.general_description_id,
                notes: values.notes || null,
                images: values.images.map((image) => image.path),
                items: values.items.map((line) => ({ ...(line.id ? { id: line.id } : {}), specific_description_id: line.specific_description_id, price: line.price.replace(/,/g, '') })),
            };
            return invoice ? api.put<{ data: GeneralInvoice }>(`${PATH}/${invoice.id}`, payload) : api.post<{ data: GeneralInvoice }>(PATH, payload);
        },
        { invalidate: ['list', 'record', 'options'], success: t('admin.actions.saved'), onSuccess: (result) => navigate(`${PATH}/${result.data.id}`), onError: form.fail },
    );

    const upload = async (files: FileList | null) => {
        for (const file of Array.from(files ?? [])) {
            setUploading((count) => count + 1);
            try {
                const body = new FormData();
                body.append('image', file);
                const image = await api.post<{ path: string; url: string }>(`${PATH}/images`, body);
                form.setValues((current) => ({ ...current, images: [...current.images, image] }));
            } catch (error) {
                toast.error(error instanceof ApiError ? (error.first('image') ?? error.message) : String(error));
            } finally {
                setUploading((count) => count - 1);
            }
        }
        if (fileInput.current) fileInput.current.value = '';
    };

    const showNotice = holdsTransfer || Boolean(form.errors.general_description_id && form.errors.general_description_id === t('filament/resources/general_invoice.money_transfer_notice.cannot_save'));

    return (
        <form onSubmit={(event) => { event.preventDefault(); save.mutate(undefined); }}>
            <PageHeader
                title={invoice ? invoice.name : `${t('admin.actions.create')} ${t('filament/resources/general_invoice.label')}`}
                breadcrumbs={[{ label: t('filament/resources/general_invoice.plural_label'), to: PATH }, { label: invoice ? t('admin.actions.edit') : t('admin.actions.create') }]}
                actions={invoice && <RecordHeaderActions record={invoice} endpoint={PATH} path={PATH} label={invoice.name} edit={false} />}
            />
            <div className="space-y-5">
                {showNotice && <Notice title={t('filament/resources/general_invoice.money_transfer_notice.heading')}>{t('filament/resources/general_invoice.money_transfer_notice.description')}</Notice>}

                <Section title={t('filament/resources/general_invoice.sections.details')} icon={<FileText className="size-4" />}>
                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <Field label={t('filament/resources/invoice.fields.name')} error={form.errors.name} required>
                            {(fieldId) => <Input id={fieldId} autoFocus value={values.name} maxLength={255} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                        </Field>
                        <Field label={t('filament/resources/invoice.fields.date')} error={form.errors.date} required>
                            {(fieldId) => <Input id={fieldId} type="date" value={values.date} onChange={(event) => form.set('date', event.target.value)} invalid={Boolean(form.errors.date)} />}
                        </Field>
                        <Field label={t('filament/resources/invoice.fields.type')} error={form.errors.type} required>
                            {() => (
                                <div>
                                    <Segmented
                                        options={types}
                                        value={values.type}
                                        onChange={(value) => {
                                            form.setValues((current) => ({ ...current, type: value, general_description_id: null, general_description_label: null, items: current.items.map((line) => ({ ...line, specific_description_id: null, label: null })) }));
                                            setHoldsTransfer(false);
                                        }}
                                    />
                                </div>
                            )}
                        </Field>
                        <Field label={t('filament/resources/invoice.fields.general_description_id')} error={form.errors.general_description_id} required hint={!values.type ? t('admin/system.general_invoices.choose_type_first') : undefined}>
                            {(fieldId) => (
                                <SearchSelect
                                    id={fieldId}
                                    endpoint="/system-options/general-descriptions"
                                    query={{ type: values.type }}
                                    value={values.general_description_id}
                                    initialLabel={values.general_description_label}
                                    disabled={!values.type}
                                    invalid={Boolean(form.errors.general_description_id)}
                                    onChange={(value, option) => {
                                        const changed = (value === null ? null : Number(value)) !== values.general_description_id;
                                        form.setValues((current) => ({
                                            ...current,
                                            general_description_id: value === null ? null : Number(value),
                                            general_description_label: option?.label ?? null,
                                            items: changed ? current.items.map((line) => ({ ...line, specific_description_id: null, label: null })) : current.items,
                                        }));
                                        form.setErrors((errors) => { const next = { ...errors }; delete next.general_description_id; return next; });
                                        setHoldsTransfer(Boolean((option as (SearchOption & { holds_transfer?: boolean }) | null)?.holds_transfer));
                                    }}
                                />
                            )}
                        </Field>
                        {invoice?.cashier && (
                            <Field label={t('filament/resources/invoice.fields.cashier_id')}>
                                {(fieldId) => <Input id={fieldId} value={invoice.cashier?.name ?? ''} disabled readOnly />}
                            </Field>
                        )}
                    </div>
                </Section>

                <Section title={t('filament/resources/general_invoice.fields.items')} icon={<ListOrdered className="size-4" />}>
                    {form.errors.items && <p className="mb-3 text-xs text-danger">{form.errors.items}</p>}
                    <div className="hidden grid-cols-[minmax(0,7fr)_minmax(0,3fr)_2.25rem] gap-3 pb-2 text-xs font-medium text-muted md:grid">
                        <span>
                            {t('filament/resources/invoice_item.fields.specific_description_id')}
                            <span className="ms-0.5 text-danger">*</span>
                        </span>
                        <span>
                            {t('filament/resources/invoice_item.fields.price')}
                            <span className="ms-0.5 text-danger">*</span>
                        </span>
                        <span />
                    </div>
                    <div className="space-y-3">
                        {values.items.map((line, index) => (
                            <div key={line.key} className="grid grid-cols-[minmax(0,1fr)_2.25rem] gap-3 rounded-lg border border-line p-3 md:grid-cols-[minmax(0,7fr)_minmax(0,3fr)_2.25rem] md:border-0 md:p-0">
                                <div className="col-span-1">
                                    <SearchSelect
                                        endpoint="/system-options/specific-descriptions"
                                        query={{ general_description_id: values.general_description_id ?? 0 }}
                                        value={line.specific_description_id}
                                        initialLabel={line.label}
                                        placeholder={values.general_description_id ? undefined : t('admin/system.general_invoices.choose_general_description_first')}
                                        disabled={!values.general_description_id}
                                        invalid={Boolean(lineError(index, 'specific_description_id'))}
                                        onChange={(value, option) => setLine(line.key, { specific_description_id: value === null ? null : Number(value), label: option?.label ?? null })}
                                    />
                                    {lineError(index, 'specific_description_id') && <p className="mt-1 text-xs text-danger">{lineError(index, 'specific_description_id')}</p>}
                                </div>
                                <div className="order-3 col-span-1 md:order-none">
                                    <Input
                                        inputMode="decimal"
                                        dir="ltr"
                                        value={line.price}
                                        placeholder="0"
                                        disabled={!values.general_description_id}
                                        aria-label={t('filament/resources/invoice_item.fields.price')}
                                        onChange={(event) => setLine(line.key, { price: event.target.value })}
                                        invalid={Boolean(lineError(index, 'price'))}
                                        className="text-end tabular-nums"
                                    />
                                    {lineError(index, 'price') && <p className="mt-1 text-xs text-danger">{lineError(index, 'price')}</p>}
                                </div>
                                <Button
                                    variant="ghost"
                                    className="size-9.5 px-0"
                                    disabled={values.items.length <= 1}
                                    aria-label={t('admin.actions.remove')}
                                    icon={<Trash2 className="size-4" />}
                                    onClick={() => form.set('items', values.items.filter((other) => other.key !== line.key))}
                                />
                            </div>
                        ))}
                    </div>
                    <Button size="sm" className="mt-3" icon={<Plus className="size-4" />} disabled={!values.general_description_id} onClick={() => form.set('items', [...values.items, emptyLine()])}>
                        {t('admin/system.general_invoices.add_line')}
                    </Button>
                </Section>

                <Section title={t('filament/resources/general_invoice.sections.amounts')} icon={<Banknote className="size-4" />}>
                    <p className="text-xs font-medium text-muted">{t('filament/resources/invoice.fields.amount')}</p>
                    <p className="mt-1 text-2xl font-semibold tracking-tight text-accent tabular-nums">{money(total)}</p>
                </Section>

                <Section title={t('filament/resources/general_invoice.sections.attachments')} icon={<Paperclip className="size-4" />}>
                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <Field label={t('filament/resources/invoice.fields.images')} error={form.errors.images ?? Object.entries(form.errors).find(([key]) => key.startsWith('images.'))?.[1]} hint={t('admin/system.general_invoices.images_hint')}>
                            {(fieldId) => (
                                <div className="space-y-3">
                                    {values.images.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {values.images.map((image) => (
                                                <div key={image.path} className="group relative size-20 overflow-hidden rounded-lg border border-line bg-subtle">
                                                    <img src={image.url} alt="" className="size-full object-cover" />
                                                    <button
                                                        type="button"
                                                        onClick={() => form.set('images', values.images.filter((other) => other.path !== image.path))}
                                                        className="absolute end-1 top-1 rounded-full bg-black/60 p-0.5 text-white opacity-90 hover:opacity-100"
                                                        aria-label={t('admin.actions.remove')}
                                                    >
                                                        <X className="size-3.5" />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    <input id={fieldId} ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={(event) => upload(event.target.files)} />
                                    <Button size="sm" icon={uploading > 0 ? <Spinner className="size-4" /> : <ImagePlus className="size-4" />} disabled={uploading > 0} onClick={() => fileInput.current?.click()}>
                                        {t('admin/system.general_invoices.add_images')}
                                    </Button>
                                </div>
                            )}
                        </Field>
                        <Field label={t('filament/resources/invoice.fields.notes')} error={form.errors.notes}>
                            {(fieldId) => <Textarea id={fieldId} rows={4} value={values.notes} onChange={(event) => form.set('notes', event.target.value)} />}
                        </Field>
                    </div>
                </Section>

                <div className="flex justify-end gap-2">
                    <ButtonLink to={invoice ? `${PATH}/${invoice.id}` : PATH}>{t('admin.actions.cancel')}</ButtonLink>
                    <Button type="submit" variant="primary" loading={save.isPending} disabled={uploading > 0 || holdsTransfer}>
                        {invoice ? t('admin.actions.save_changes') : t('admin.actions.create')}
                    </Button>
                </div>
            </div>
        </form>
    );
}

export function GeneralInvoicePage() {
    const t = useT();
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: GeneralInvoice }>(`${PATH}/${id}`) });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const invoice = data.data;

    return (
        <>
            <PageHeader
                title={invoice.name}
                breadcrumbs={[{ label: t('filament/resources/general_invoice.plural_label'), to: PATH }, { label: `#${invoice.id}` }]}
                badge={
                    <>
                        <EnumBadge value={invoice.type} />
                        {invoice.is_money_transfer && <Badge icon={<Lock className="size-3" />}>{t('admin/system.general_invoices.read_only')}</Badge>}
                        {invoice.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                    </>
                }
                actions={<RecordHeaderActions record={invoice} endpoint={PATH} path={PATH} label={invoice.name} />}
            />
            <RecordTabs
                subjectType="invoice"
                subjectId={invoice.id}
                tabs={[
                    {
                        value: 'details',
                        label: t('admin/system.tabs.details'),
                        content: (
                            <div className="space-y-5">
                                {invoice.is_money_transfer && <Notice title={t('filament/resources/general_invoice.money_transfer_notice.heading')}>{t('filament/resources/general_invoice.money_transfer_notice.description')}</Notice>}
                                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                                    <Section title={t('filament/resources/general_invoice.sections.details')} icon={<FileText className="size-4" />} className="lg:col-span-2">
                                        <DescriptionList
                                            columns={2}
                                            items={[
                                                { label: t('filament/resources/invoice.fields.name'), value: invoice.name },
                                                { label: t('filament/resources/invoice.fields.date'), value: <span className="tabular-nums">{date(invoice.date)}</span> },
                                                { label: t('filament/resources/invoice.fields.type'), value: <EnumBadge value={invoice.type} /> },
                                                {
                                                    label: t('filament/resources/invoice.fields.general_description_id'),
                                                    value: invoice.general_description ? (
                                                        <ButtonLink to={`/general-descriptions/${invoice.general_description.id}`} variant="ghost" size="sm" className="-ms-2.5">
                                                            {invoice.general_description.name}
                                                        </ButtonLink>
                                                    ) : '—',
                                                },
                                                { label: t('filament/resources/invoice.fields.cashier_id'), value: invoice.cashier?.name, hidden: !invoice.cashier },
                                                { label: t('filament/resources/invoice.fields.user_id'), value: invoice.user?.name },
                                            ]}
                                        />
                                    </Section>
                                    <Section title={t('filament/resources/general_invoice.sections.amounts')} icon={<Banknote className="size-4" />}>
                                        <p className="text-xs font-medium text-muted">{t('filament/resources/invoice.fields.amount')}</p>
                                        <p className="mt-1 text-2xl font-semibold tracking-tight text-accent tabular-nums">{money(invoice.amount)}</p>
                                    </Section>
                                </div>
                                <section className="card overflow-hidden">
                                    <div className="flex items-center gap-2 border-b border-line px-5 py-3.5 text-[15px] font-semibold">
                                        <ListOrdered className="size-4 text-muted" />
                                        {t('filament/resources/general_invoice.fields.items')}
                                    </div>
                                    {(invoice.items ?? []).length === 0 ? (
                                        <Empty title={t('admin/system.general_invoices.no_lines')} />
                                    ) : (
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                                                    <th className="px-5 py-2.5 text-start font-medium">{t('filament/resources/invoice_item.fields.specific_description_id')}</th>
                                                    <th className="px-5 py-2.5 text-end font-medium">{t('filament/resources/invoice_item.fields.price')}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {(invoice.items ?? []).map((line) => (
                                                    <tr key={line.id} className="border-b border-line last:border-0">
                                                        <td className="px-5 py-3">{line.specific_description ?? '—'}</td>
                                                        <td className="px-5 py-3 text-end tabular-nums">{number(line.price)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot>
                                                <tr className="border-t border-line bg-subtle/40 font-semibold">
                                                    <td className="px-5 py-3">{t('admin.table.total')}</td>
                                                    <td className="px-5 py-3 text-end tabular-nums">{money(invoice.amount)}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    )}
                                </section>
                                <Section title={t('filament/resources/general_invoice.sections.attachments')} icon={<Paperclip className="size-4" />}>
                                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                        <div>
                                            <p className="mb-2 text-xs font-medium text-muted">{t('filament/resources/invoice.fields.images')}</p>
                                            {(invoice.images ?? []).length === 0 ? (
                                                <p className="text-sm text-faint">{t('admin/system.general_invoices.no_images')}</p>
                                            ) : (
                                                <div className="flex flex-wrap gap-2">
                                                    {(invoice.images ?? []).map((image) => (
                                                        <a key={image.path} href={image.url} target="_blank" rel="noreferrer" className="block size-24 overflow-hidden rounded-lg border border-line bg-subtle hover:opacity-90">
                                                            <img src={image.url} alt="" className="size-full object-cover" />
                                                        </a>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                        <div>
                                            <p className="mb-2 text-xs font-medium text-muted">{t('filament/resources/invoice.fields.notes')}</p>
                                            <p className={cn('text-sm whitespace-pre-line', !invoice.notes && 'text-faint')}>{invoice.notes || '—'}</p>
                                        </div>
                                    </div>
                                </Section>
                            </div>
                        ),
                    },
                ]}
            />
        </>
    );
}
