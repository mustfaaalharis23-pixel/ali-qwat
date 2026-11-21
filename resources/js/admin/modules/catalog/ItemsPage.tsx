import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { ImageOff, Plus, RotateCcw, Tag, Trash2 } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, money, number } from '../../lib/format';
import { useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { ButtonLink, ConfirmDialog, toast } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { MetricView, type MetricPayload } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { useRecordActions, type Abilities } from '../../components/RecordActions';
import { ITEM_INVALIDATE, SetCategoryModal } from './ItemActions';
import { ItemExcel } from './ItemExcel';

export interface ItemRow {
    id: number;
    name: string;
    image_url: string | null;
    supplier_id: number | null;
    supplier?: { id: number; name: string } | null;
    item_category_id: number | null;
    category?: { id: number; name: string } | null;
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
    update?: boolean;
    delete?: boolean;
    restore?: boolean;
    force_delete?: boolean;
}

export function ItemThumb({ url, large = false, fill = false, className }: { url: string | null; large?: boolean; fill?: boolean; className?: string }) {
    const frame = cn('shrink-0', fill ? 'aspect-square w-full' : cn('ring-1 ring-line', large ? 'h-20 w-28 rounded-lg' : 'size-9 rounded-md'), className);
    const roomy = large || fill;

    return url ? (
        <img src={url} alt="" loading="lazy" className={cn(frame, roomy ? 'bg-subtle object-contain' : 'object-cover')} />
    ) : (
        <span className={cn(frame, 'flex items-center justify-center bg-subtle text-faint')}>
            <ImageOff className={roomy ? 'size-6' : 'size-4'} />
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
    const categoryId = params.get('category') ? Number(params.get('category')) : null;
    const [bulk, setBulk] = useState<{ kind: BulkKind; ids: (string | number)[]; clear: () => void } | null>(null);
    const [assigningCategory, setAssigningCategory] = useState<{ ids: number[]; clear: () => void } | null>(null);
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

    /** The supplier and category filters live in the address, so a filtered list can be shared or opened again. */
    const setParam = (name: 'supplier' | 'category') => (value: number | string | null) =>
        setParams(
            (current) => {
                const next = new URLSearchParams(current);
                if (value === null) next.delete(name);
                else next.set(name, String(value));
                next.delete('page');
                return next;
            },
            { replace: true },
        );

    return (
        <>
            <PageHeader title={t('filament/resources/item.plural_label')} actions={can.create && <ItemExcel />} />
            <ItemCountCards metrics={summary?.data} />
            <DataTable<ItemRow>
                endpoint="/items"
                trashed
                showColumnPicker={false}
                query={{ supplier_id: supplierId ?? undefined, filter: { item_category_id: categoryId ?? undefined } }}
                defaultSort={{ column: 'id', direction: 'desc' }}
                rowHref={(item) => `/items/${item.id}`}
                cardImage={(item) => <ItemThumb url={item.image_url} fill />}
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
                                <SearchSelect endpoint="/options/item-categories" value={categoryId} onChange={setParam('category')} placeholder={t('filament/resources/item.fields.item_category_id')} />
                            </div>
                            <div className="w-52">
                                <SearchSelect endpoint="/options/suppliers" value={supplierId} onChange={setParam('supplier')} placeholder={t('filament/resources/item.fields.supplier_id')} />
                            </div>
                            {can.create && (
                                <ButtonLink to="/items/create" variant="primary" size="sm" icon={<Plus className="size-4" />}>
                                    {t('admin.actions.new')}
                                </ButtonLink>
                            )}
                        </>
                    );
                }}
                selectable={Boolean(can.update || can.delete || can.restore || can.force_delete)}
                selectionActions={({ ids, rows, clear }) => [
                    {
                        label: t('admin/catalog.assign_category'),
                        icon: <Tag className="size-4" />,
                        hidden: !can.update,
                        disabled: rows.some((item) => item.deleted_at !== null || !item.can.update),
                        onSelect: () => setAssigningCategory({ ids: rows.map((item) => item.id), clear }),
                    },
                    { label: '', divider: true, hidden: !can.update },
                    {
                        label: t('admin.actions.restore'),
                        icon: <RotateCcw className="size-4" />,
                        hidden: !can.restore,
                        onSelect: () => setBulk({ kind: 'restore', ids, clear }),
                    },
                    {
                        label: t('admin.actions.delete'),
                        icon: <Trash2 className="size-4" />,
                        tone: 'danger',
                        hidden: !can.delete,
                        onSelect: () => setBulk({ kind: 'delete', ids, clear }),
                    },
                    {
                        label: t('admin.actions.force_delete'),
                        icon: <Trash2 className="size-4" />,
                        tone: 'danger',
                        hidden: !can.force_delete,
                        onSelect: () => setBulk({ kind: 'force_delete', ids, clear }),
                    },
                ]}
                columns={[
                    {
                        key: 'image',
                        header: t('filament/resources/item.fields.image'),
                        className: 'w-36',
                        cardHidden: true,
                        cell: (item) => <ItemThumb url={item.image_url} large />,
                    },
                    {
                        key: 'name',
                        header: t('filament/resources/item.fields.name'),
                        sortable: true,
                        cell: (item) => <span className="block min-w-32 text-[14.5px] font-semibold whitespace-normal break-words">{item.name}</span>,
                    },
                    {
                        key: 'available_quantity',
                        header: t('filament/resources/item.fields.available_quantity'),
                        sortable: true,
                        align: 'end',
                        cell: (item) => <span dir="ltr" className={cn('tabular-nums', item.available_quantity < 0 && 'text-danger')}>{number(item.available_quantity)}</span>,
                    },
                    {
                        key: 'incoming_quantity',
                        header: t('filament/resources/item.fields.incoming_quantity'),
                        sortable: true,
                        align: 'end',
                        cell: (item) => <span className="tabular-nums">{number(item.incoming_quantity)}</span>,
                    },
                    {
                        key: 'sale_price',
                        header: t('filament/resources/item.fields.sale_price'),
                        sortable: true,
                        align: 'end',
                        cardHidden: true,
                        cell: (item) => <span className="text-[15px] font-semibold whitespace-nowrap tabular-nums">{money(item.sale_price)}</span>,
                    },
                ]}
                rowActions={(item) => actions.items(item, { edit: () => navigate(`/items/${item.id}/edit`) })}
            />
            {actions.dialogs}
            {assigningCategory && (
                <SetCategoryModal ids={assigningCategory.ids} onDone={assigningCategory.clear} onClose={() => setAssigningCategory(null)} />
            )}
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
