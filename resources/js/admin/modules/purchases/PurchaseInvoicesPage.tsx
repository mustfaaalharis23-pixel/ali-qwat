import { Link } from 'react-router';
import { Plus, RotateCcw, Trash2 } from 'lucide-react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../lib/api';
import { date, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Button, toast } from '../../components/ui';
import { DataTable, type Column } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { RemoteMetric } from '../../components/MetricCard';
import { EnumBadge, RouteButton, SplitBadge, SupplierPie, type PurchaseInvoiceRow } from './shared';
import { useInvoiceActions } from './invoiceActions';

export const STATUSES = ['pending', 'importer_confirmed', 'confirmed'];
export const STAGES = ['in_production', 'manufactured', 'received_at_warehouse', 'on_the_way', 'received'];

/** The columns of a list of purchase invoices, shared by the purchase invoices page and a supplier's page. */
export function usePurchaseInvoiceColumns(options: { supplier?: boolean } = {}): Column<PurchaseInvoiceRow>[] {
    const t = useT();
    const field = (name: string) => t(`filament/resources/invoice.fields.${name}`);

    return [
        { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (row) => <span className="text-muted tabular-nums">{row.id}</span> },
        {
            key: 'name',
            header: field('name'),
            cell: (row) => (
                <div className="flex flex-col items-start gap-1">
                    <span className="flex items-center gap-2 font-medium">
                        {row.name}
                        {row.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                    </span>
                    <SplitBadge split={row.split} />
                </div>
            ),
        },
        { key: 'supplier', header: field('supplier_id'), hidden: options.supplier === false, cell: (row) => row.supplier?.name ?? '—' },
        { key: 'category', header: field('invoice_category_id'), cell: (row) => (row.category ? <Badge>{row.category.name}</Badge> : <span className="text-faint">—</span>) },
        {
            key: 'container',
            header: field('container_id'),
            cell: (row) =>
                row.container ? (
                    row.container.can_view ? (
                        <Link to={`/containers/${row.container.id}`} onClick={(event) => event.stopPropagation()} className="text-accent hover:underline" dir="ltr">
                            {row.container.name}
                        </Link>
                    ) : (
                        <span dir="ltr">{row.container.name}</span>
                    )
                ) : (
                    <span className="text-faint">—</span>
                ),
        },
        { key: 'date', header: field('date'), sortable: true, cell: (row) => <span className="tabular-nums">{date(row.date)}</span> },
        { key: 'amount', header: field('amount'), sortable: true, align: 'end', cell: (row) => <span className="tabular-nums">{number(row.amount)}</span> },
        { key: 'paid_amount', header: field('paid_amount'), align: 'end', cell: (row) => <span className="tabular-nums">{number(row.paid_amount)}</span> },
        {
            key: 'remaining',
            header: field('remaining'),
            sortable: true,
            align: 'end',
            cell: (row) => <span className={row.remaining > 0 ? 'font-medium text-danger tabular-nums' : 'tabular-nums'}>{number(row.remaining)}</span>,
        },
        { key: 'status', header: field('status'), cell: (row) => <EnumBadge value={row.status} /> },
        { key: 'stage', header: field('stage'), cell: (row) => <EnumBadge value={row.stage} /> },
        { key: 'awaited_confirmation', header: field('awaited_confirmation'), cell: (row) => <EnumBadge value={row.awaited_confirmation} tone="gray" /> },
    ];
}

export function PurchaseInvoicesPage() {
    const t = useT();
    const client = useQueryClient();
    const actions = useInvoiceActions();
    const columns = usePurchaseInvoiceColumns();
    const { data: suppliers } = useQuery({
        queryKey: ['options', '/options/suppliers', 'filter'],
        queryFn: () => api.get<{ data: { value: number; label: string }[] }>('/options/suppliers'),
        staleTime: 60_000,
    });

    const bulk = async (ids: (string | number)[], kind: 'delete' | 'restore', clear: () => void) => {
        const results = await Promise.allSettled(ids.map((id) => (kind === 'delete' ? api.delete(`/purchase-invoices/${id}`) : api.post(`/purchase-invoices/${id}/restore`))));
        const failed = results.filter((result) => result.status === 'rejected').length;
        if (failed > 0) toast.error(t('admin/purchases.bulk.failed', { count: failed }));
        else toast.success(kind === 'delete' ? t('admin.actions.deleted') : t('admin.actions.restored'));
        clear();
        client.invalidateQueries({ predicate: (query) => query.queryKey[0] === 'list' || query.queryKey[0] === 'metric' });
    };

    return (
        <>
            <PageHeader title={t('filament/resources/purchase_invoice.plural_label')} />
            <div className="mb-6 grid gap-4 lg:grid-cols-3">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <RemoteMetric endpoint="/purchase-invoices/metrics/pending" />
                    <RemoteMetric endpoint="/purchase-invoices/metrics/importer_confirmed" />
                </div>
                <SupplierPie endpoint="/purchase-invoices/chart" className="lg:col-span-2" />
            </div>
            <DataTable<PurchaseInvoiceRow>
                endpoint="/purchase-invoices"
                trashed
                defaultSort={{ column: 'date', direction: 'desc' }}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <RouteButton to="/purchase-invoices/create" variant="primary" size="sm" icon={<Plus className="size-4" />}>
                            {t('admin.actions.new')}
                        </RouteButton>
                    )
                }
                filters={[
                    { key: 'supplier_id', label: t('filament/resources/invoice.fields.supplier_id'), options: (suppliers?.data ?? []).map((option) => ({ value: option.value, label: option.label })) },
                    { key: 'status', label: t('filament/resources/invoice.fields.status'), options: STATUSES.map((value) => ({ value, label: t(`filament/resources/invoice.statuses.${value}`) })) },
                    { key: 'stage', label: t('filament/resources/invoice.fields.stage'), options: STAGES.map((value) => ({ value, label: t(`filament/resources/invoice.stages.${value}`) })) },
                ]}
                columns={columns}
                rowHref={(row) => `/purchase-invoices/${row.id}`}
                rowActions={(row) => actions.items(row)}
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
