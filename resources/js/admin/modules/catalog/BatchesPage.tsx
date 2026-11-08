import { Link, useSearchParams } from 'react-router';
import { date, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, toneOf } from '../../components/ui';
import { DataTable, type Column } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';

interface BatchRow {
    id: number;
    item: { id: number; name: string } | null;
    lens_power: string | null;
    colors: { color: string | null; pieces: number }[];
    invoice_id: number;
    invoice_path: string | null;
    date: string | null;
    quantity: number;
    purchase_price: number;
    status: { value: string; label: string; color: string } | null;
    deleted_at: string | null;
}

const STATUSES = ['pending', 'importer_confirmed', 'confirmed'];

/**
 * The batches table, read only: a batch is written with its purchase line. On an item's page (itemId) the item column is hidden,
 * because it is always the same item, and the table keeps its own state in the address (stateKey).
 */
export function BatchesTable({ itemId, stateKey }: { itemId?: number; stateKey?: string }) {
    const t = useT();
    const [params, setParams] = useSearchParams();
    const powerParam = stateKey ? `${stateKey}_power` : 'power';
    const powerId = params.get(powerParam) ? Number(params.get(powerParam)) : null;

    const setPower = (value: number | string | null) =>
        setParams(
            (current) => {
                const next = new URLSearchParams(current);
                if (value === null) next.delete(powerParam);
                else next.set(powerParam, String(value));
                next.delete(stateKey ? `${stateKey}_page` : 'page');
                return next;
            },
            { replace: true },
        );

    const columns: Column<BatchRow>[] = [
        { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (batch) => <span className="text-muted tabular-nums">{batch.id}</span> },
        {
            key: 'item',
            header: t('filament/resources/batch.fields.item_id'),
            hidden: itemId !== undefined,
            cell: (batch) =>
                batch.item ? (
                    <Link to={`/items/${batch.item.id}`} className="font-medium hover:text-accent">
                        {batch.item.name}
                    </Link>
                ) : (
                    '—'
                ),
        },
        { key: 'lens_power', header: t('filament/resources/batch.fields.lens_power_id'), cell: (batch) => (batch.lens_power ? <Badge tone="info">{batch.lens_power}</Badge> : <span className="text-faint">—</span>) },
        {
            key: 'colors',
            header: t('filament/resources/batch.fields.colors'),
            className: 'min-w-40',
            cell: (batch) =>
                batch.colors.length === 0 ? (
                    <span className="text-faint">—</span>
                ) : (
                    <span className="text-[13px]">
                        {batch.colors.map((color) => t('filament/resources/invoice_item.group.color', { color: color.color, pieces: number(color.pieces) })).join(t('filament/resources/invoice_item.group.separator'))}
                    </span>
                ),
        },
        {
            key: 'invoice_id',
            header: t('filament/resources/batch.fields.invoice_id'),
            sortable: true,
            cell: (batch) =>
                batch.invoice_path ? (
                    <Link to={batch.invoice_path} className="font-medium text-accent tabular-nums hover:underline">
                        {batch.invoice_id}
                    </Link>
                ) : (
                    <span className="tabular-nums">{batch.invoice_id}</span>
                ),
        },
        { key: 'date', header: t('filament/resources/batch.fields.date'), cell: (batch) => <span className="tabular-nums whitespace-nowrap">{date(batch.date)}</span> },
        { key: 'quantity', header: t('filament/resources/batch.fields.quantity'), sortable: true, align: 'end', cell: (batch) => <span className="tabular-nums">{number(batch.quantity)}</span> },
        { key: 'purchase_price', header: t('filament/resources/batch.fields.purchase_price'), sortable: true, align: 'end', cell: (batch) => <span className="tabular-nums">{number(batch.purchase_price)}</span> },
        {
            key: 'status',
            header: t('filament/resources/batch.fields.status'),
            cell: (batch) => (
                <span className="inline-flex items-center gap-1.5">
                    {batch.status && <Badge tone={toneOf(batch.status.color)}>{batch.status.label}</Badge>}
                    {batch.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                </span>
            ),
        },
    ];

    return (
        <DataTable<BatchRow>
            endpoint="/batches"
            stateKey={stateKey}
            trashed
            query={{ item_id: itemId, lens_power_id: powerId ?? undefined }}
            defaultSort={{ column: 'id', direction: 'desc' }}
            filters={[
                {
                    key: 'status',
                    label: t('filament/resources/batch.fields.status'),
                    options: STATUSES.map((status) => ({ value: status, label: t(`filament/resources/invoice.statuses.${status}`) })),
                },
            ]}
            toolbar={
                <div className="w-44">
                    <SearchSelect endpoint="/options/lens-powers" value={powerId} onChange={setPower} placeholder={t('filament/resources/batch.fields.lens_power_id')} />
                </div>
            }
            columns={columns}
        />
    );
}

/** The batches list at /batches. It is not in the sidebar: batches are mostly seen on an item's page. */
export function BatchesPage() {
    const t = useT();
    return (
        <>
            <PageHeader title={t('filament/resources/batch.plural_label')} />
            <BatchesTable />
        </>
    );
}
