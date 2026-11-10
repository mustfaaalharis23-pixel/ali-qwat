import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Banknote, Building2, FileText, Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { api } from '../../lib/api';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, Card, CardBody, CardHeader, DescriptionList, Field, Input, Modal, MoneyInput, PageSpinner, Tabs, Textarea, toast } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { RemoteMetric } from '../../components/MetricCard';
import { useRecordActions, type Abilities } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { FileField, SupplierPie, type PurchaseInvoiceRow } from './shared';
import { usePurchaseInvoiceColumns } from './PurchaseInvoicesPage';
import { AuditsPanel, useCanViewAudits } from '../system/AuditsPanel';

interface Supplier {
    id: number;
    name_ar: string;
    name_zh: string | null;
    phone: string | null;
    email: string | null;
    contract: string | null;
    contract_url: string | null;
    notes: string | null;
    deleted_at: string | null;
    can: Abilities & { pay?: boolean };
}

const field = (name: string) => `filament/resources/supplier.fields.${name}`;

/** Pays one amount on the supplier's invoices, from the oldest to the newest, up to what it is owed. */
function PayDialog({ supplier, onClose }: { supplier: Supplier; onClose: () => void }) {
    const t = useT();
    const key = (name: string) => t(`filament/resources/supplier.actions.distribute_payoff.${name}`, { supplier: supplier.name_ar });
    const { data } = useQuery({ queryKey: ['purchases', 'supplier-debt-text', supplier.id], queryFn: () => api.get<{ debt: number; description: string }>(`/suppliers/${supplier.id}/debt`), gcTime: 0 });
    const form = useForm({ amount: '', date: new Date().toISOString().slice(0, 10) });
    const pay = useSave((body: typeof form.values) => api.post<{ message: string }>(`/suppliers/${supplier.id}/distribute-payoff`, body), {
        invalidate: ['list', 'record', 'metric', 'purchases'],
        onSuccess: (result) => {
            toast.success(result.message);
            onClose();
        },
        onError: form.fail,
    });

    return (
        <Modal
            open
            onClose={onClose}
            title={key('heading')}
            description={data?.description}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={pay.isPending} onClick={() => pay.mutate(form.values)}>
                        {key('label')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label={key('amount')} error={form.errors.amount} required>
                    {(id) => <MoneyInput id={id} autoFocus value={form.values.amount} onChange={(value) => form.set('amount', value)} invalid={Boolean(form.errors.amount)} />}
                </Field>
                <Field label={key('date')} error={form.errors.date} required>
                    {(id) => <Input id={id} type="date" dir="ltr" value={form.values.date} onChange={(event) => form.set('date', event.target.value)} invalid={Boolean(form.errors.date)} />}
                </Field>
            </div>
        </Modal>
    );
}

export function SuppliersPage() {
    const t = useT();
    const navigate = useNavigate();
    const client = useQueryClient();
    const [paying, setPaying] = useState<Supplier | null>(null);
    const actions = useRecordActions<Supplier>('/suppliers', { label: (supplier) => supplier.name_ar });

    const bulk = async (ids: (string | number)[], kind: 'delete' | 'restore', clear: () => void) => {
        const results = await Promise.allSettled(ids.map((id) => (kind === 'delete' ? api.delete(`/suppliers/${id}`) : api.post(`/suppliers/${id}/restore`))));
        const failed = results.filter((result) => result.status === 'rejected').length;
        if (failed > 0) toast.error(t('admin/purchases.bulk.failed', { count: failed }));
        else toast.success(kind === 'delete' ? t('admin.actions.deleted') : t('admin.actions.restored'));
        clear();
        client.invalidateQueries({ predicate: (query) => query.queryKey[0] === 'list' });
    };

    return (
        <>
            <PageHeader title={t('filament/resources/supplier.plural_label')} />
            <div className="mb-6 grid gap-4 md:grid-cols-2">
                <RemoteMetric endpoint="/suppliers/metrics/total_debt" className="self-start" />
                <SupplierPie endpoint="/suppliers/chart" />
            </div>
            <DataTable<Supplier>
                endpoint="/suppliers"
                trashed
                defaultSort={{ column: 'id', direction: 'desc' }}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <ButtonLink to="/suppliers/create" variant="primary" size="sm" icon={<Plus className="size-4" />}>
                            {t('admin.actions.new')}
                        </ButtonLink>
                    )
                }
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (supplier) => <span className="text-muted tabular-nums">{supplier.id}</span> },
                    {
                        key: 'name_ar',
                        header: t(field('name_ar')),
                        sortable: true,
                        cell: (supplier) => (
                            <span className="flex items-center gap-2 font-medium">
                                {supplier.name_ar}
                                {supplier.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'name_zh', header: t(field('name_zh')), sortable: true, cell: (supplier) => supplier.name_zh ?? '—' },
                    { key: 'phone', header: t(field('phone')), cell: (supplier) => <span dir="ltr">{supplier.phone ?? '—'}</span> },
                    { key: 'email', header: t(field('email')), cell: (supplier) => supplier.email ?? '—' },
                ]}
                rowHref={(supplier) => `/suppliers/${supplier.id}`}
                rowActions={(supplier) => [
                    { label: t('filament/resources/supplier.actions.distribute_payoff.label'), icon: <Banknote className="size-4" />, onSelect: () => setPaying(supplier), hidden: !supplier.can.pay },
                    ...actions.items(supplier, { edit: () => navigate(`/suppliers/${supplier.id}/edit`) }),
                ]}
                selectable
                bulkActions={(selected, clear) => (
                    <>
                        <Button size="sm" variant="danger" icon={<Trash2 className="size-4" />} onClick={() => bulk(selected, 'delete', clear)}>
                            {t('admin.actions.delete')}
                        </Button>
                        <Button size="sm" icon={<RotateCcw className="size-4" />} onClick={() => bulk(selected, 'restore', clear)}>
                            {t('admin.actions.restore')}
                        </Button>
                    </>
                )}
            />
            {paying && <PayDialog supplier={paying} onClose={() => setPaying(null)} />}
            {actions.dialogs}
        </>
    );
}

function SupplierForm({ supplier }: { supplier?: Supplier }) {
    const t = useT();
    const navigate = useNavigate();
    const form = useForm({
        name_ar: supplier?.name_ar ?? '',
        name_zh: supplier?.name_zh ?? '',
        phone: supplier?.phone ?? '',
        email: supplier?.email ?? '',
        contract: supplier?.contract && supplier.contract_url ? [{ path: supplier.contract, url: supplier.contract_url }] : ([] as { path: string; url: string }[]),
        notes: supplier?.notes ?? '',
    });
    const save = useSave(
        (values: typeof form.values) => {
            const body = { ...values, contract: values.contract[0]?.path ?? null };
            return supplier ? api.put<{ data: Supplier }>(`/suppliers/${supplier.id}`, body) : api.post<{ data: Supplier }>('/suppliers', body);
        },
        { invalidate: ['list', 'record', 'options'], success: t('admin.actions.saved'), onSuccess: (result) => navigate(`/suppliers/${result.data.id}`), onError: form.fail },
    );
    const input = (name: 'name_ar' | 'name_zh' | 'phone' | 'email', required = false, dir?: 'ltr') => (
        <Field label={t(field(name))} error={form.errors[name]} required={required}>
            {(id) => <Input id={id} dir={dir} value={form.values[name]} onChange={(event) => form.set(name, event.target.value)} invalid={Boolean(form.errors[name])} />}
        </Field>
    );

    return (
        <form
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                save.mutate(form.values);
            }}
        >
            <Card>
                <CardHeader title={t('filament/resources/supplier.sections.details')} icon={<Building2 className="size-4.5" />} />
                <CardBody className="grid gap-4 md:grid-cols-2">
                    {input('name_ar', true)}
                    {input('name_zh')}
                    {input('phone', false, 'ltr')}
                    {input('email', false, 'ltr')}
                </CardBody>
            </Card>
            <Card>
                <CardHeader title={t('filament/resources/supplier.sections.documents')} icon={<FileText className="size-4.5" />} />
                <CardBody className="grid gap-4 md:grid-cols-2">
                    <Field label={t(field('contract'))} error={form.errors.contract}>
                        {() => <FileField endpoint="/suppliers/uploads" accept="application/pdf,image/jpeg,image/png,image/webp" files={form.values.contract} onChange={(files) => form.set('contract', files)} />}
                    </Field>
                    <Field label={t(field('notes'))} error={form.errors.notes}>
                        {(id) => <Textarea id={id} rows={4} value={form.values.notes} onChange={(event) => form.set('notes', event.target.value)} />}
                    </Field>
                </CardBody>
            </Card>
            <div className="flex justify-end gap-2">
                <Button onClick={() => navigate(supplier ? `/suppliers/${supplier.id}` : '/suppliers')}>{t('admin.actions.cancel')}</Button>
                <Button type="submit" variant="primary" loading={save.isPending}>
                    {supplier ? t('admin.actions.save_changes') : t('admin.actions.create')}
                </Button>
            </div>
        </form>
    );
}

function useSupplier() {
    const { id } = useParams();
    return useQuery({
        queryKey: ['record', '/suppliers', id],
        queryFn: () => api.get<{ data: Supplier; can: { pay: boolean; view_invoices: boolean; view_items: boolean } }>(`/suppliers/${id}`),
    });
}

export function CreateSupplierPage() {
    const t = useT();
    return (
        <>
            <PageHeader title={t('admin/purchases.suppliers.create_title')} breadcrumbs={[{ label: t('filament/resources/supplier.plural_label'), to: '/suppliers' }, { label: t('admin.actions.create') }]} />
            <SupplierForm />
        </>
    );
}

export function EditSupplierPage() {
    const t = useT();
    const navigate = useNavigate();
    const { data, error, isLoading } = useSupplier();
    const actions = useRecordActions<Supplier>('/suppliers', { label: (supplier) => supplier.name_ar, onDeleted: () => navigate('/suppliers') });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const supplier = data.data;

    return (
        <>
            <PageHeader
                title={`${t('admin.actions.edit')}: ${supplier.name_ar}`}
                breadcrumbs={[{ label: t('filament/resources/supplier.plural_label'), to: '/suppliers' }, { label: supplier.name_ar, to: `/suppliers/${supplier.id}` }, { label: t('admin.actions.edit') }]}
                actions={actions.items(supplier).filter((item) => !item.hidden).map((item, index) => (
                    <Button key={index} variant={item.tone === 'danger' ? 'danger' : 'secondary'} icon={item.icon} onClick={item.onSelect}>
                        {item.label}
                    </Button>
                ))}
            />
            <SupplierForm key={supplier.id} supplier={supplier} />
            {actions.dialogs}
        </>
    );
}

interface SupplierItem {
    id: number;
    name: string;
    image_url: string | null;
    is_active: boolean;
}

export function SupplierPage() {
    const t = useT();
    const { data, error, isLoading } = useSupplier();
    const [tab, setTab] = useState('items');
    const [paying, setPaying] = useState(false);
    const invoiceColumns = usePurchaseInvoiceColumns({ supplier: false });
    const { id } = useParams();
    const canViewAudits = useCanViewAudits('supplier', id);

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const supplier = data.data;
    const tabs = [
        ...(data.can.view_items ? [{ value: 'items', label: t('filament/resources/item.plural_label') }] : []),
        ...(data.can.view_invoices ? [{ value: 'invoices', label: t('filament/resources/purchase_invoice.plural_label') }] : []),
        ...(canViewAudits ? [{ value: 'audits', label: t('filament/resources/audit.plural_label') }] : []),
    ];
    const activeTab = tabs.some((item) => item.value === tab) ? tab : tabs[0]?.value;

    return (
        <>
            <PageHeader
                title={supplier.name_ar}
                description={supplier.name_zh}
                breadcrumbs={[{ label: t('filament/resources/supplier.plural_label'), to: '/suppliers' }, { label: `#${supplier.id}` }]}
                badge={supplier.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                actions={
                    <>
                        {data.can.pay && (
                            <Button icon={<Banknote className="size-4" />} onClick={() => setPaying(true)}>
                                {t('filament/resources/supplier.actions.distribute_payoff.label')}
                            </Button>
                        )}
                        {supplier.can.update && (
                            <ButtonLink to={`/suppliers/${supplier.id}/edit`} variant="primary" icon={<Pencil className="size-4" />}>
                                {t('admin.actions.edit')}
                            </ButtonLink>
                        )}
                    </>
                }
            />
            <div className="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {['debt', 'paid', 'purchases', 'items_count'].map((key) => (
                    <RemoteMetric key={key} endpoint={`/suppliers/${supplier.id}/metrics/${key}`} />
                ))}
            </div>
            <Card className="mb-5">
                <CardHeader title={t('filament/resources/supplier.sections.details')} icon={<Building2 className="size-4.5" />} />
                <CardBody>
                    <DescriptionList
                        items={[
                            { label: t(field('name_ar')), value: supplier.name_ar },
                            { label: t(field('name_zh')), value: supplier.name_zh },
                            { label: t(field('phone')), value: supplier.phone && <span dir="ltr">{supplier.phone}</span> },
                            { label: t(field('email')), value: supplier.email },
                            {
                                label: t(field('contract')),
                                value: supplier.contract_url && (
                                    <a href={supplier.contract_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-accent hover:underline">
                                        <FileText className="size-4" />
                                        {t('admin.actions.download')}
                                    </a>
                                ),
                            },
                            { label: t(field('notes')), value: supplier.notes && <span className="whitespace-pre-line">{supplier.notes}</span> },
                        ]}
                    />
                </CardBody>
            </Card>
            {activeTab && (
                <>
                    <Tabs tabs={tabs} value={activeTab} onChange={setTab} className="mb-3" />
                    {activeTab === 'audits' ? (
                        <AuditsPanel subjectType="supplier" subjectId={supplier.id} />
                    ) : activeTab === 'items' ? (
                        <DataTable<SupplierItem>
                            key="items"
                            endpoint={`/suppliers/${supplier.id}/items`}
                            stateKey="items"
                            defaultSort={{ column: 'id', direction: 'asc' }}
                            rowHref={(item) => `/items/${item.id}`}
                            columns={[
                                { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (item) => <span className="text-muted tabular-nums">{item.id}</span> },
                                {
                                    key: 'image',
                                    header: t('filament/resources/item.fields.image'),
                                    className: 'w-20',
                                    cell: (item) => (item.image_url ? <img src={item.image_url} alt="" className="size-9 rounded-md border border-line object-cover" /> : <span className="text-faint">—</span>),
                                },
                                { key: 'name', header: t('filament/resources/item.fields.name'), sortable: true, cell: (item) => <span className="font-medium">{item.name}</span> },
                                {
                                    key: 'is_active',
                                    header: t('filament/resources/item.fields.is_active'),
                                    cell: (item) => <Badge tone={item.is_active ? 'success' : 'gray'}>{item.is_active ? t('admin.actions.yes') : t('admin.actions.no')}</Badge>,
                                },
                            ]}
                        />
                    ) : (
                        <DataTable<PurchaseInvoiceRow>
                            key="invoices"
                            endpoint={`/suppliers/${supplier.id}/invoices`}
                            stateKey="invoices"
                            defaultSort={{ column: 'date', direction: 'desc' }}
                            rowHref={(invoice) => `/purchase-invoices/${invoice.id}`}
                            columns={invoiceColumns.filter((column) => ['id', 'name', 'date', 'amount', 'paid_amount', 'remaining', 'status'].includes(column.key))}
                        />
                    )}
                </>
            )}
            {paying && <PayDialog supplier={supplier} onClose={() => setPaying(false)} />}
        </>
    );
}
