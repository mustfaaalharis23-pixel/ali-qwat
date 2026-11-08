import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Archive, Container as ContainerIcon, Info, LogOut, Pencil, Plus, RotateCcw, Scissors, Trash2, TriangleAlert, Truck } from 'lucide-react';
import { api } from '../../lib/api';
import { date, number } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, Card, CardBody, CardHeader, ConfirmDialog, DescriptionList, Field, Input, Modal, PageSpinner, Textarea, toast } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { RemoteMetric } from '../../components/MetricCard';
import { SearchSelect, type SearchOption } from '../../components/SearchSelect';
import { useRecordActions, type Abilities } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { dollars, EnumBadge, Facts, toNumber, type EnumValue, type Fact } from './shared';

interface Container {
    id: number;
    name: string;
    international_number: string | null;
    cbm_price: number | null;
    loading_date: string | null;
    expected_arrival_date: string | null;
    notes: string | null;
    total_cbm: number;
    transport_cost: number;
    has_invoices: boolean;
    deleted_at: string | null;
    can: Abilities & { change_invoices: boolean };
}

const field = (name: string) => `filament/resources/container.fields.${name}`;
const DAYS_TO_ARRIVAL = 60;

function useBulk(endpoint: string) {
    const t = useT();
    const client = useQueryClient();
    return async (ids: (string | number)[], kind: 'delete' | 'restore', clear: () => void) => {
        const results = await Promise.allSettled(ids.map((id) => (kind === 'delete' ? api.delete(`${endpoint}/${id}`) : api.post(`${endpoint}/${id}/restore`))));
        const failed = results.filter((result) => result.status === 'rejected').length;
        if (failed > 0) toast.error(t('admin/purchases.bulk.failed', { count: failed }));
        else toast.success(kind === 'delete' ? t('admin.actions.deleted') : t('admin.actions.restored'));
        clear();
        client.invalidateQueries({ predicate: (query) => query.queryKey[0] === 'list' });
    };
}

export function ContainersPage() {
    const t = useT();
    const navigate = useNavigate();
    const actions = useRecordActions<Container>('/containers', { label: (container) => container.name });
    const bulk = useBulk('/containers');

    return (
        <>
            <PageHeader title={t('filament/resources/container.plural_label')} />
            <DataTable<Container>
                endpoint="/containers"
                trashed
                defaultSort={{ column: 'id', direction: 'desc' }}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <ButtonLink to="/containers/create" variant="primary" size="sm" icon={<Plus className="size-4" />}>
                            {t('admin.actions.new')}
                        </ButtonLink>
                    )
                }
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (container) => <span className="text-muted tabular-nums">{container.id}</span> },
                    {
                        key: 'international_number',
                        header: t(field('international_number')),
                        cell: (container) => (
                            <span className="flex items-center gap-2 font-medium" dir="ltr">
                                {container.international_number ?? '—'}
                                {container.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'cbm_price', header: t(field('cbm_price')), sortable: true, align: 'end', cell: (container) => <span className="tabular-nums">{dollars(container.cbm_price)}</span> },
                    { key: 'total_cbm', header: t(field('total_cbm')), align: 'end', cell: (container) => <span className="tabular-nums">{number(container.total_cbm, 3)}</span> },
                    { key: 'transport_cost', header: t(field('transport_cost')), align: 'end', cell: (container) => <span className="tabular-nums">{dollars(container.transport_cost)}</span> },
                    { key: 'loading_date', header: t(field('loading_date')), sortable: true, cell: (container) => <span className="tabular-nums">{date(container.loading_date)}</span> },
                    { key: 'expected_arrival_date', header: t(field('expected_arrival_date')), sortable: true, cell: (container) => <span className="tabular-nums">{date(container.expected_arrival_date)}</span> },
                ]}
                rowHref={(container) => `/containers/${container.id}`}
                rowActions={(container) => actions.items(container, { edit: () => navigate(`/containers/${container.id}/edit`) })}
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
            {actions.dialogs}
        </>
    );
}

/** The box on top of the form that says how a container is really worked out. */
function HowItWorks() {
    const t = useT();
    return (
        <div className="flex items-start gap-3 rounded-xl border border-info/30 bg-info-soft p-3.5 text-sm">
            <Info className="mt-0.5 size-4 shrink-0 text-info" />
            <div>
                <p className="font-medium">{t('filament/resources/container.how_it_works.heading')}</p>
                <p className="text-muted">{t('filament/resources/container.how_it_works.description')}</p>
            </div>
        </div>
    );
}

function addDays(value: string, days: number): string {
    const parsed = new Date(`${value}T00:00:00Z`);
    if (Number.isNaN(parsed.getTime())) return '';
    parsed.setUTCDate(parsed.getUTCDate() + days);
    return parsed.toISOString().slice(0, 10);
}

function ContainerForm({ container }: { container?: Container }) {
    const t = useT();
    const navigate = useNavigate();
    const form = useForm({
        international_number: container?.international_number ?? '',
        cbm_price: container?.cbm_price === null || container?.cbm_price === undefined ? '' : String(container.cbm_price),
        loading_date: container?.loading_date ?? '',
        expected_arrival_date: container?.expected_arrival_date ?? '',
        notes: container?.notes ?? '',
    });
    const save = useSave((values: typeof form.values) => (container ? api.put<{ data: Container }>(`/containers/${container.id}`, values) : api.post<{ data: Container }>('/containers', values)), {
        invalidate: ['list', 'record', 'options', 'metric'],
        success: t('admin.actions.saved'),
        onSuccess: (result) => navigate(`/containers/${result.data.id}`),
        onError: form.fail,
    });

    return (
        <form
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                save.mutate(form.values);
            }}
        >
            <HowItWorks />
            <Card>
                <CardBody className="grid gap-4 md:grid-cols-2">
                    {container && (
                        <Field label={t(field('id'))}>
                            {(id) => <Input id={id} value={container.id} disabled />}
                        </Field>
                    )}
                    <Field label={t(field('international_number'))} error={form.errors.international_number} hint={t('filament/resources/container.hints.international_number')}>
                        {(id) => (
                            <Input
                                id={id}
                                dir="ltr"
                                placeholder="MSCU1234567"
                                value={form.values.international_number}
                                onChange={(event) => form.set('international_number', event.target.value.toUpperCase())}
                                invalid={Boolean(form.errors.international_number)}
                            />
                        )}
                    </Field>
                    <Field label={t(field('cbm_price'))} error={form.errors.cbm_price} hint={t('filament/resources/container.hints.cbm_price')} required>
                        {(id) => <Input id={id} dir="ltr" inputMode="decimal" value={form.values.cbm_price} onChange={(event) => form.set('cbm_price', event.target.value)} invalid={Boolean(form.errors.cbm_price)} />}
                    </Field>
                    <Field label={t(field('loading_date'))} error={form.errors.loading_date}>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                dir="ltr"
                                value={form.values.loading_date}
                                onChange={(event) => {
                                    form.set('loading_date', event.target.value);
                                    form.set('expected_arrival_date', event.target.value ? addDays(event.target.value, DAYS_TO_ARRIVAL) : '');
                                }}
                            />
                        )}
                    </Field>
                    <Field label={t(field('expected_arrival_date'))} error={form.errors.expected_arrival_date} hint={t('filament/resources/container.hints.expected_arrival_date', { days: DAYS_TO_ARRIVAL })}>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                dir="ltr"
                                min={form.values.loading_date || undefined}
                                value={form.values.expected_arrival_date}
                                onChange={(event) => form.set('expected_arrival_date', event.target.value)}
                                invalid={Boolean(form.errors.expected_arrival_date)}
                            />
                        )}
                    </Field>
                    <Field label={t(field('notes'))} error={form.errors.notes} className="md:col-span-2">
                        {(id) => <Textarea id={id} rows={4} value={form.values.notes} onChange={(event) => form.set('notes', event.target.value)} />}
                    </Field>
                </CardBody>
            </Card>
            <div className="flex justify-end gap-2">
                <Button onClick={() => navigate(container ? `/containers/${container.id}` : '/containers')}>{t('admin.actions.cancel')}</Button>
                <Button type="submit" variant="primary" loading={save.isPending}>
                    {container ? t('admin.actions.save_changes') : t('admin.actions.create')}
                </Button>
            </div>
        </form>
    );
}

function useContainer() {
    const { id } = useParams();
    return useQuery({
        queryKey: ['record', '/containers', id],
        queryFn: () => api.get<{ data: Container; can: { view_invoices: boolean } }>(`/containers/${id}`),
    });
}

export function CreateContainerPage() {
    const t = useT();
    return (
        <>
            <PageHeader title={t('admin/purchases.containers.create_title')} breadcrumbs={[{ label: t('filament/resources/container.plural_label'), to: '/containers' }, { label: t('admin.actions.create') }]} />
            <ContainerForm />
        </>
    );
}

export function EditContainerPage() {
    const t = useT();
    const navigate = useNavigate();
    const { data, error, isLoading } = useContainer();
    const actions = useRecordActions<Container>('/containers', { label: (container) => container.name, onDeleted: () => navigate('/containers') });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const container = data.data;

    return (
        <>
            <PageHeader
                title={`${t('admin.actions.edit')}: ${container.name}`}
                breadcrumbs={[{ label: t('filament/resources/container.plural_label'), to: '/containers' }, { label: container.name, to: `/containers/${container.id}` }, { label: t('admin.actions.edit') }]}
                actions={actions
                    .items(container)
                    .filter((item) => !item.hidden)
                    .map((item, index) => (
                        <Button key={index} variant={item.tone === 'danger' ? 'danger' : 'secondary'} icon={item.icon} onClick={item.onSelect}>
                            {item.label}
                        </Button>
                    ))}
            />
            <ContainerForm key={container.id} container={container} />
            {actions.dialogs}
        </>
    );
}

interface ContainerInvoice {
    id: number;
    name: string;
    supplier: string | null;
    date: string | null;
    cbm: number;
    cbm_price: number | null;
    transport_cost_usd: number | null;
    status: EnumValue | null;
}

/** Adds purchase invoices that are in no container to this one. */
function AddInvoicesDialog({ containerId, onClose }: { containerId: number; onClose: () => void }) {
    const t = useT();
    const [chosen, setChosen] = useState<SearchOption[]>([]);
    const add = useSave((ids: number[]) => api.post<{ message: string }>(`/containers/${containerId}/invoices`, { ids }), {
        invalidate: ['list', 'record', 'metric', 'options'],
        onSuccess: (result) => {
            toast.success(result.message);
            onClose();
        },
    });

    return (
        <Modal
            open
            onClose={onClose}
            title={t('filament/resources/container.actions.add_invoices')}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" disabled={chosen.length === 0} loading={add.isPending} onClick={() => add.mutate(chosen.map((option) => Number(option.value)))}>
                        {t('admin.actions.add')}
                    </Button>
                </>
            }
        >
            <div className="space-y-3">
                <SearchSelect
                    endpoint={`/containers/${containerId}/invoice-options`}
                    value={null}
                    placeholder={t('filament/resources/purchase_invoice.plural_label')}
                    onChange={(_, option) => option && !chosen.some((other) => other.value === option.value) && setChosen([...chosen, option])}
                />
                {chosen.length > 0 && (
                    <div className="divide-y divide-line rounded-lg border border-line">
                        {chosen.map((option) => (
                            <div key={option.value} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">{option.label}</span>
                                    {option.description && <span className="block truncate text-xs text-muted">{option.description}</span>}
                                </span>
                                <Button size="sm" variant="ghost" icon={<Trash2 className="size-4" />} aria-label={t('admin.actions.remove')} onClick={() => setChosen(chosen.filter((other) => other.value !== option.value))} />
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </Modal>
    );
}

interface SplitLine {
    id: number;
    name: string;
    image_url: string | null;
    cartons_count: number;
    boxes_count: number;
    boxes_per_carton: number;
    pieces_per_packet: number;
    quantity: number;
    groups: string[];
    colors: string | null;
    /** The packets each count of cartons carries, from the split itself. */
    packets_by_cartons: number[];
}

/** Ships part of an invoice in this container: the cartons of each line that go become a new invoice in it. */
function AddPartDialog({ containerId, onClose }: { containerId: number; onClose: () => void }) {
    const t = useT();
    const key = (name: string, replace = {}) => t(`filament/resources/container.actions.add_part_of_invoice.${name}`, replace);
    const [invoiceId, setInvoiceId] = useState<number | null>(null);
    const [cartons, setCartons] = useState<Record<number, string>>({});
    const { data: invoices } = useQuery({ queryKey: ['options', 'splittable', containerId], queryFn: () => api.get<{ data: SearchOption[] }>(`/containers/${containerId}/splittable`) });
    const { data: lines, isFetching } = useQuery({
        queryKey: ['options', 'splittable-lines', containerId, invoiceId],
        queryFn: () => api.get<{ lines: SplitLine[] }>(`/containers/${containerId}/splittable/${invoiceId}`),
        enabled: invoiceId !== null,
    });
    const split = useSave((body: { invoice_id: number; cartons: Record<number, number> }) => api.post<{ message: string }>(`/containers/${containerId}/split`, body), {
        invalidate: ['list', 'record', 'metric', 'options'],
        onSuccess: (result) => {
            toast.success(result.message);
            onClose();
        },
    });

    const preview = (line: SplitLine): Fact[] => {
        const value = Math.trunc(toNumber(cartons[line.id]));
        const amounts = (c: number, p: number) => ({ cartons: number(c), packets: number(p), pieces: number(p * line.pieces_per_packet) });
        if (value > line.cartons_count) return [{ text: key('preview.too_many', { count: number(line.cartons_count) }), tone: 'danger', icon: <TriangleAlert className="size-3" /> }];
        if (value <= 0) return [{ text: key('preview.nothing'), tone: 'gray', icon: <Archive className="size-3" /> }];
        const packets = line.packets_by_cartons[value] ?? line.boxes_count;
        if (value === line.cartons_count) return [{ text: key('preview.all', amounts(value, packets)), tone: 'success', icon: <Truck className="size-3" /> }];
        return [
            { text: key('preview.goes', amounts(value, packets)), tone: 'warning', icon: <Truck className="size-3" /> },
            { text: key('preview.stays', amounts(line.cartons_count - value, line.boxes_count - packets)), tone: 'gray', icon: <Archive className="size-3" /> },
        ];
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="xl"
            title={key('label')}
            description={key('description')}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button
                        variant="primary"
                        disabled={invoiceId === null}
                        loading={split.isPending}
                        onClick={() =>
                            invoiceId !== null &&
                            split.mutate({ invoice_id: invoiceId, cartons: Object.fromEntries(Object.entries(cartons).filter(([, value]) => value !== '').map(([id, value]) => [id, Math.trunc(toNumber(value))])) })
                        }
                    >
                        {t('admin.actions.confirm')}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                <Field label={key('invoice')} required>
                    {(id) => (
                        <select
                            id={id}
                            value={invoiceId ?? ''}
                            onChange={(event) => {
                                setInvoiceId(event.target.value ? Number(event.target.value) : null);
                                setCartons({});
                            }}
                            className="h-9.5 w-full rounded-lg border border-line bg-surface px-3 text-sm"
                        >
                            <option value="">{t('admin.form.choose')}</option>
                            {(invoices?.data ?? []).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label} · {option.description}
                                </option>
                            ))}
                        </select>
                    )}
                </Field>
                {isFetching && <p className="text-sm text-muted">…</p>}
                {lines?.lines.map((line) => (
                    <div key={line.id} className="flex flex-col gap-3 rounded-xl border border-line p-3 sm:flex-row">
                        {line.image_url ? <img src={line.image_url} alt={line.name} className="size-24 shrink-0 rounded-lg border border-line object-cover" /> : <div className="size-24 shrink-0 rounded-lg bg-subtle" />}
                        <div className="min-w-0 flex-1 space-y-2">
                            <p className="text-base font-semibold">{line.name}</p>
                            <Facts
                                facts={[
                                    ...line.groups.map((group): Fact => ({ text: group, tone: 'accent' })),
                                    ...(line.colors ? [{ text: line.colors, tone: 'gray' } as Fact] : []),
                                    { text: key('line.cartons', { value: number(line.cartons_count) }), tone: 'info' },
                                    { text: key('line.packets_per_carton', { value: number(line.boxes_per_carton) }), tone: 'gray' },
                                    { text: key('line.pieces_per_packet', { value: number(line.pieces_per_packet) }), tone: 'gray' },
                                    { text: key('line.pieces', { value: number(line.quantity) }), tone: 'gray' },
                                ]}
                            />
                            <Field label={key('cartons')} hint={<Facts facts={preview(line)} />}>
                                {(id) => (
                                    <div className="flex max-w-xs items-stretch" dir="ltr">
                                        <Input id={id} inputMode="numeric" placeholder="0" value={cartons[line.id] ?? ''} onChange={(event) => setCartons({ ...cartons, [line.id]: event.target.value })} className="rounded-e-none" />
                                        <span className="flex items-center rounded-e-lg border border-s-0 border-line bg-subtle px-2.5 text-xs whitespace-nowrap text-muted">{key('cartons_hint', { count: line.cartons_count })}</span>
                                    </div>
                                )}
                            </Field>
                        </div>
                    </div>
                ))}
            </div>
        </Modal>
    );
}

export function ContainerPage() {
    const t = useT();
    const { data, error, isLoading } = useContainer();
    const [dialog, setDialog] = useState<'add' | 'part' | null>(null);
    const [takingOut, setTakingOut] = useState<{ ids: number[]; clear?: () => void } | null>(null);
    const takeOut = useSave((ids: number[]) => api.post<{ message: string }>(`/containers/${data?.data.id}/invoices/take-out`, { ids }), {
        invalidate: ['list', 'record', 'metric', 'options'],
        onSuccess: (result) => {
            toast.success(result.message);
            takingOut?.clear?.();
            setTakingOut(null);
        },
    });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const container = data.data;
    const canChange = container.can.change_invoices;
    const invoiceField = (name: string) => t(`filament/resources/invoice.fields.${name}`);

    return (
        <>
            <PageHeader
                title={container.name}
                breadcrumbs={[{ label: t('filament/resources/container.plural_label'), to: '/containers' }, { label: `#${container.id}` }]}
                badge={container.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                actions={
                    container.can.update && (
                        <ButtonLink to={`/containers/${container.id}/edit`} variant="primary" icon={<Pencil className="size-4" />}>
                            {t('admin.actions.edit')}
                        </ButtonLink>
                    )
                }
            />
            <div className="mb-5 grid gap-4 md:grid-cols-2">
                <RemoteMetric endpoint={`/containers/${container.id}/metrics/cbm`} />
                <RemoteMetric endpoint={`/containers/${container.id}/metrics/transport_cost`} />
            </div>
            <Card className="mb-5">
                <CardHeader title={t('filament/resources/container.label')} icon={<ContainerIcon className="size-4.5" />} />
                <CardBody className="space-y-4">
                    <DescriptionList
                        items={[
                            { label: t(field('id')), value: container.id },
                            { label: t(field('international_number')), value: container.international_number && <span dir="ltr">{container.international_number}</span> },
                            { label: t(field('cbm_price')), value: dollars(container.cbm_price) },
                            { label: t(field('loading_date')), value: date(container.loading_date) },
                            { label: t(field('expected_arrival_date')), value: date(container.expected_arrival_date) },
                            { label: t(field('notes')), value: container.notes && <span className="whitespace-pre-line">{container.notes}</span> },
                        ]}
                    />
                    <HowItWorks />
                </CardBody>
            </Card>
            {data.can.view_invoices && (
                <Card>
                    <CardHeader
                        title={t('filament/resources/purchase_invoice.plural_label')}
                        description={t('filament/resources/container.invoices_description')}
                        actions={
                            canChange && (
                                <>
                                    <Button size="sm" icon={<Scissors className="size-4" />} onClick={() => setDialog('part')}>
                                        {t('filament/resources/container.actions.add_part_of_invoice.label')}
                                    </Button>
                                    <Button size="sm" variant="primary" icon={<Plus className="size-4" />} onClick={() => setDialog('add')}>
                                        {t('filament/resources/container.actions.add_invoices')}
                                    </Button>
                                </>
                            )
                        }
                    />
                    <DataTable<ContainerInvoice>
                        endpoint={`/containers/${container.id}/invoices`}
                        stateKey="invoices"
                        className="rounded-none border-0 shadow-none"
                        defaultSort={{ column: 'date', direction: 'desc' }}
                        rowHref={(invoice) => `/purchase-invoices/${invoice.id}`}
                        columns={[
                            { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (invoice) => <span className="text-muted tabular-nums">{invoice.id}</span> },
                            { key: 'name', header: invoiceField('name'), cell: (invoice) => <span className="font-medium">{invoice.name}</span> },
                            { key: 'supplier', header: invoiceField('supplier_id'), cell: (invoice) => invoice.supplier ?? '—' },
                            { key: 'date', header: invoiceField('date'), sortable: true, cell: (invoice) => <span className="tabular-nums">{date(invoice.date)}</span> },
                            { key: 'cbm', header: invoiceField('cbm'), sortable: true, align: 'end', cell: (invoice) => <span className="tabular-nums">{number(invoice.cbm, 3)}</span> },
                            { key: 'cbm_price', header: invoiceField('cbm_price'), align: 'end', cell: (invoice) => <span className="tabular-nums">{dollars(invoice.cbm_price)}</span> },
                            { key: 'transport_cost_usd', header: invoiceField('transport_cost'), align: 'end', cell: (invoice) => <span className="tabular-nums">{dollars(invoice.transport_cost_usd)}</span> },
                            { key: 'status', header: invoiceField('status'), cell: (invoice) => <EnumBadge value={invoice.status} /> },
                        ]}
                        rowActions={(invoice) => [
                            { label: t('filament/resources/container.actions.take_invoice_out'), icon: <LogOut className="size-4" />, tone: 'danger', onSelect: () => setTakingOut({ ids: [invoice.id] }), hidden: !canChange },
                        ]}
                        selectable={canChange}
                        bulkActions={(selected, clear) => (
                            <Button size="sm" variant="danger" icon={<LogOut className="size-4" />} onClick={() => setTakingOut({ ids: selected.map(Number), clear })}>
                                {t('filament/resources/container.actions.take_invoices_out')}
                            </Button>
                        )}
                    />
                </Card>
            )}
            {dialog === 'add' && <AddInvoicesDialog containerId={container.id} onClose={() => setDialog(null)} />}
            {dialog === 'part' && <AddPartDialog containerId={container.id} onClose={() => setDialog(null)} />}
            <ConfirmDialog
                open={takingOut !== null}
                onClose={() => setTakingOut(null)}
                onConfirm={() => takingOut && takeOut.mutate(takingOut.ids)}
                loading={takeOut.isPending}
                title={t('filament/resources/container.actions.take_invoice_out')}
                confirmLabel={t('filament/resources/container.actions.take_invoice_out')}
            />
        </>
    );
}
