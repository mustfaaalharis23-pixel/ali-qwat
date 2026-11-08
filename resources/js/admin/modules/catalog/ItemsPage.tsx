import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { ImageOff, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, number } from '../../lib/format';
import { useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, ConfirmDialog, toast } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { MetricView, type MetricPayload } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { useRecordActions, type Abilities } from '../../components/RecordActions';
import { ITEM_INVALIDATE } from './ItemActions';

export interface ItemRow {
    id: number;
    name: string;
    image_url: string | null;
    supplier_id: number | null;
    supplier?: { id: number; name: string } | null;
    pieces_per_packet: number | null;
    sale_price: number;
    piece_sale_price: number;
    cost_price?: number;
    piece_cost_price?: number;
    profit_margin?: number;
    available_quantity: number;
    incoming_quantity: number;
    is_active: boolean;
    description: string | null;
    deleted_at: string | null;
    can: Abilities;
}

interface ListAbilities {
    create?: boolean;
    delete?: boolean;
    restore?: boolean;
    force_delete?: boolean;
    view_cost_price?: boolean;
}

/** The item's picture, small and square, or a quiet placeholder. */
export function ItemThumb({ url, className }: { url: string | null; className?: string }) {
    return url ? (
        <img src={url} alt="" loading="lazy" className={cn('size-9 shrink-0 rounded-md object-cover ring-1 ring-line', className)} />
    ) : (
        <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-md bg-subtle text-faint ring-1 ring-line', className)}>
            <ImageOff className="size-4" />
        </span>
    );
}

interface ListMetrics {
    data: MetricPayload[];
    can: ListAbilities;
}

/** The cards above the items list: all the items, the active ones, the inactive ones, and the ones without a supplier. */
function ItemCountCards({ metrics }: { metrics?: MetricPayload[] }) {
    return (
        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            {(metrics ?? Array.from({ length: 4 }).map(() => undefined)).map((metric, index) => (
                <MetricView key={metric?.key ?? index} metric={metric} />
            ))}
        </div>
    );
}

type BulkKind = 'delete' | 'restore' | 'force_delete';

export function ItemsPage() {
    const t = useT();
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const { data: summary } = useQuery({ queryKey: ['metric', 'items-list'], queryFn: () => api.get<ListMetrics>('/items/metrics') });
    const can = summary?.can ?? {};
    const supplierId = params.get('supplier') ? Number(params.get('supplier')) : null;
    const [bulk, setBulk] = useState<{ kind: BulkKind; ids: (string | number)[]; clear: () => void } | null>(null);
    const actions = useRecordActions<ItemRow>('/items', { label: (item) => item.name });

    const runBulk = useSave((pending: { kind: BulkKind; ids: (string | number)[] }) => api.post<{ count: number }>('/items/bulk', { action: pending.kind, ids: pending.ids }), {
        invalidate: ITEM_INVALIDATE,
        onSuccess: (result) => {
            bulk?.clear();
            setBulk(null);
            toast.success(t('admin/catalog.done_for', { count: result.count }));
        },
    });

    const bulkLabel = (kind: BulkKind) => (kind === 'restore' ? t('admin.actions.restore') : kind === 'force_delete' ? t('admin.actions.force_delete') : t('admin.actions.delete'));

    const setSupplier = (value: number | string | null) =>
        setParams(
            (current) => {
                const next = new URLSearchParams(current);
                if (value === null) next.delete('supplier');
                else next.set('supplier', String(value));
                next.delete('page');
                return next;
            },
            { replace: true },
        );

    return (
        <>
            <PageHeader title={t('filament/resources/item.plural_label')} />
            <ItemCountCards metrics={summary?.data} />
            <DataTable<ItemRow>
                endpoint="/items"
                trashed
                query={{ supplier_id: supplierId ?? undefined }}
                defaultSort={{ column: 'id', direction: 'desc' }}
                rowHref={(item) => `/items/${item.id}`}
                filters={[
                    {
                        key: 'status',
                        label: t('filament/resources/item.filters.status.active'),
                        options: [
                            { value: 'all', label: t('filament/resources/item.filters.status.all') },
                            { value: 'inactive', label: t('filament/resources/item.filters.status.inactive') },
                        ],
                    },
                ]}
                toolbar={() => {
                    return (
                        <>
                            <div className="w-52">
                                <SearchSelect endpoint="/options/suppliers" value={supplierId} onChange={setSupplier} placeholder={t('filament/resources/item.fields.supplier_id')} />
                            </div>
                            {can.create && (
                                <ButtonLink to="/items/create" variant="primary" size="sm" icon={<Plus className="size-4" />}>
                                    {t('admin.actions.new')}
                                </ButtonLink>
                            )}
                        </>
                    );
                }}
                selectable={Boolean(can.delete || can.restore || can.force_delete)}
                bulkActions={(ids, clear) => (
                    <>
                        {can.restore && (
                            <Button size="sm" variant="secondary" icon={<RotateCcw className="size-3.5" />} onClick={() => setBulk({ kind: 'restore', ids, clear })}>
                                {t('admin.actions.restore')}
                            </Button>
                        )}
                        {can.delete && (
                            <Button size="sm" variant="danger" icon={<Trash2 className="size-3.5" />} onClick={() => setBulk({ kind: 'delete', ids, clear })}>
                                {t('admin.actions.delete')}
                            </Button>
                        )}
                        {can.force_delete && (
                            <Button size="sm" variant="danger" icon={<Trash2 className="size-3.5" />} onClick={() => setBulk({ kind: 'force_delete', ids, clear })}>
                                {t('admin.actions.force_delete')}
                            </Button>
                        )}
                    </>
                )}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (item) => <span className="text-muted tabular-nums">{item.id}</span> },
                    {
                        key: 'name',
                        header: t('filament/resources/item.fields.name'),
                        sortable: true,
                        cell: (item) => (
                            <span className="flex min-w-48 items-center gap-3">
                                <ItemThumb url={item.image_url} />
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">{item.name}</span>
                                    {item.supplier && <span className="block truncate text-xs text-muted">{item.supplier.name}</span>}
                                </span>
                                {item.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'available_quantity', header: t('filament/resources/item.fields.available_quantity'), sortable: true, align: 'end', cell: (item) => <span className={cn('tabular-nums', item.available_quantity < 0 && 'text-danger')}>{number(item.available_quantity)}</span> },
                    { key: 'incoming_quantity', header: t('filament/resources/item.fields.incoming_quantity'), sortable: true, align: 'end', cell: (item) => <span className="text-muted tabular-nums">{number(item.incoming_quantity)}</span> },
                    { key: 'sale_price', header: t('filament/resources/item.fields.sale_price'), sortable: true, align: 'end', cell: (item) => <span className="tabular-nums">{number(item.sale_price)}</span> },
                    { key: 'cost_price', header: t('filament/resources/item.fields.cost_price'), sortable: true, align: 'end', hidden: !can.view_cost_price, cell: (item) => (item.cost_price === undefined ? null : <span className="tabular-nums">{number(item.cost_price)}</span>) },
                    {
                        key: 'profit_margin',
                        header: t('filament/resources/item.fields.profit_margin'),
                        align: 'end',
                        hidden: !can.view_cost_price,
                        cell: (item) => (item.profit_margin === undefined ? null : <span className={cn('tabular-nums', item.profit_margin < 0 && 'text-danger')}>{number(item.profit_margin)}</span>),
                    },
                    {
                        key: 'is_active',
                        header: t('filament/resources/item.fields.is_active'),
                        align: 'center',
                        cell: (item) => <Badge tone={item.is_active ? 'success' : 'gray'}>{item.is_active ? t('admin/catalog.active') : t('admin/catalog.inactive')}</Badge>,
                    },
                ]}
                rowActions={(item) => actions.items(item, { edit: () => navigate(`/items/${item.id}/edit`) })}
            />
            {actions.dialogs}
            <ConfirmDialog
                open={bulk !== null}
                onClose={() => setBulk(null)}
                onConfirm={() => bulk && runBulk.mutate({ kind: bulk.kind, ids: bulk.ids })}
                loading={runBulk.isPending}
                tone={bulk?.kind === 'restore' ? 'primary' : 'danger'}
                title={bulk ? `${bulkLabel(bulk.kind)}: ${t('admin.table.selected', { count: bulk.ids.length })}` : ''}
                description={bulk?.kind === 'delete' ? t('admin.confirm.delete_description') : bulk?.kind === 'force_delete' ? t('admin.confirm.force_delete_description') : t('admin.confirm.are_you_sure')}
                confirmLabel={bulk ? bulkLabel(bulk.kind) : undefined}
            />
        </>
    );
}
