import { useState, type ReactNode } from 'react';
import { useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Building2, CircleCheck, RefreshCw, Tag } from 'lucide-react';
import { api, type Paginated } from '../../lib/api';
import { cn, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Button, Card, Skeleton, toneOf, type MenuItem } from '../../components/ui';
import { DataTable, type Column } from '../../components/DataTable';
import { InfoButton, MetricView, type MetricPayload } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import { ActivateDialog, RecalculateStockDialog, SetSalePriceModal, SetSupplierModal } from './ItemActions';
import { ItemThumb } from './ItemsPage';

interface LensColumn {
    name: string;
    label: string;
    type: 'text' | 'image' | 'boolean';
    sortable: boolean;
    searchable: boolean;
    numeric: boolean;
    badge: boolean;
}

interface LensCell {
    value: unknown;
    color: string | null;
}

interface LensRow {
    id: number;
    path: string | null;
    cells: Record<string, LensCell>;
    can: { update: boolean };
}

interface LensAnswer extends Paginated<LensRow> {
    lens: { key: string; label: string; explanation: string; guidance: string };
    columns: LensColumn[];
    actions: string[];
    bulk_actions: string[];
    metrics: MetricPayload[];
}

type Pending = { action: string; row: LensRow } | { action: 'setSupplierForSelected'; ids: number[]; clear: () => void };

const textTone: Record<string, string> = { danger: 'text-danger', success: 'text-success', warning: 'text-warning', info: 'text-info', gray: 'text-muted', primary: 'text-accent' };

/** One cell as the lens's column draws it: a picture, a yes or no, a number, or a text, in the color the lens gives it. */
function LensCellView({ column, cell }: { column: LensColumn; cell?: LensCell }) {
    const t = useT();
    const value = cell?.value;
    if (column.type === 'image') return <ItemThumb url={typeof value === 'string' ? value : null} />;
    if (column.type === 'boolean') return <Badge tone={value ? 'success' : 'gray'}>{value ? t('admin.actions.yes') : t('admin.actions.no')}</Badge>;
    if (value === null || value === undefined || value === '') return <span className="text-faint">—</span>;
    const text = column.numeric ? number(value as number) : String(value);
    if (column.badge) return <Badge tone={toneOf(cell?.color)}>{text}</Badge>;
    if (column.name === 'id') return <span className="text-muted tabular-nums">{text}</span>;
    return <span className={cn(column.numeric && 'tabular-nums', column.name === 'name' && 'font-medium', cell?.color && textTone[cell.color])}>{text}</span>;
}

/**
 * Every lens of the panel on one page, at /lenses/:key: the lens decides its title, its info, its columns, its rows, its actions,
 * and its cards, and this page only draws them. The actions are the lens's own: fix the price, activate, work the stock out again,
 * and set the supplier, one row or the selected rows at once.
 */
export function LensPage() {
    const t = useT();
    const { key = '' } = useParams();
    const endpoint = `/lenses/${key}`;
    const [pending, setPending] = useState<Pending | null>(null);
    const { data: meta, error } = useQuery({ queryKey: ['list', 'lens-meta', key], queryFn: () => api.get<LensAnswer>(endpoint, { per_page: 1 }) });

    if (error) return <QueryError error={error} />;
    if (!meta) {
        return (
            <>
                <Skeleton className="mb-6 h-8 w-64" />
                <Skeleton className="h-96 rounded-[var(--radius-card)]" />
            </>
        );
    }

    const name = (row: LensRow) => String(row.cells.name?.value ?? row.id);
    const actionLabels: Record<string, { label: string; icon: ReactNode }> = {
        setSalePrice: { label: t('filament/resources/item.actions.set_sale_price.label'), icon: <Tag className="size-4" /> },
        activate: { label: t('filament/resources/item.actions.activate.label'), icon: <CircleCheck className="size-4" /> },
        recalculateStock: { label: t('filament/resources/item.actions.recalculate_stock.label'), icon: <RefreshCw className="size-4" /> },
        setSupplier: { label: t('filament/resources/item.actions.set_supplier.label'), icon: <Building2 className="size-4" /> },
    };
    const rowActions = (row: LensRow): MenuItem[] =>
        meta.actions.filter((action) => actionLabels[action]).map((action) => ({ ...actionLabels[action], hidden: !row.can.update, onSelect: () => setPending({ action, row }) }));
    const hasRowActions = meta.actions.some((action) => actionLabels[action]);
    const canSelect = meta.bulk_actions.includes('setSupplierForSelected');

    const columns: Column<LensRow>[] = meta.columns.map((column) => ({
        key: column.name,
        header: column.name === 'id' ? '#' : column.label,
        sortable: column.sortable,
        align: column.numeric ? 'end' : column.type === 'boolean' ? 'center' : 'start',
        className: column.name === 'id' ? 'w-16' : column.type === 'image' ? 'w-14' : undefined,
        cell: (row: LensRow) => <LensCellView column={column} cell={row.cells[column.name]} />,
    }));

    const cellNumber = (row: LensRow, name: string) => Number(row.cells[name]?.value ?? 0);

    return (
        <>
            <PageHeader
                title={meta.lens.label}
                description={meta.lens.explanation}
                badge={<InfoButton title={meta.lens.label} explanation={meta.lens.explanation} example={meta.lens.guidance} />}
            />

            {meta.metrics.length > 0 && (
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {meta.metrics.map((metric) => (
                        <MetricView key={metric.key} metric={metric} />
                    ))}
                </div>
            )}

            <Card className="mb-4 border-info/25 bg-info-soft/30 px-4 py-3">
                <p className="text-xs font-medium text-info">{t('filament/lenses.info.what_to_do')}</p>
                <p className="mt-0.5 text-[13px] leading-6 text-ink">{meta.lens.guidance}</p>
            </Card>

            <DataTable<LensRow>
                key={key}
                endpoint={endpoint}
                searchable={meta.columns.some((column) => column.searchable)}
                columns={columns}
                rowHref={(row) => row.path}
                rowActions={hasRowActions ? rowActions : undefined}
                selectable={canSelect}
                bulkActions={(ids, clear) =>
                    canSelect && (
                        <Button size="sm" variant="primary" icon={<Building2 className="size-3.5" />} onClick={() => setPending({ action: 'setSupplierForSelected', ids: ids.map(Number), clear })}>
                            {t('filament/resources/item.actions.set_supplier.label')}
                        </Button>
                    )
                }
            />

            {pending?.action === 'setSalePrice' && 'row' in pending && (
                <SetSalePriceModal
                    item={{ id: pending.row.id, name: name(pending.row), sale_price: cellNumber(pending.row, 'sale_price'), cost_price: cellNumber(pending.row, 'cost_price') }}
                    onClose={() => setPending(null)}
                />
            )}
            {pending?.action === 'activate' && 'row' in pending && <ActivateDialog item={{ id: pending.row.id, name: name(pending.row) }} onClose={() => setPending(null)} />}
            {pending?.action === 'recalculateStock' && 'row' in pending && <RecalculateStockDialog item={{ id: pending.row.id, name: name(pending.row) }} onClose={() => setPending(null)} />}
            {pending?.action === 'setSupplier' && 'row' in pending && <SetSupplierModal ids={[pending.row.id]} onClose={() => setPending(null)} />}
            {pending && 'ids' in pending && <SetSupplierModal ids={pending.ids} onClose={() => setPending(null)} onDone={pending.clear} />}
        </>
    );
}
