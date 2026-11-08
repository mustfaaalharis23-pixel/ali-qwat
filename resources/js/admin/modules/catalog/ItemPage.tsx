import { useState, type ReactNode } from 'react';
import { useParams, useSearchParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Box, Eye, FileText, Pencil, Palette } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, ButtonLink, Card, CardBody, CardHeader, DescriptionList, PageSpinner, Tabs } from '../../components/ui';
import { MetricView, type MetricPayload } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import { BatchesTable } from './BatchesPage';
import { ItemThumb, type ItemRow } from './ItemsPage';

const PIECES_IN_A_DOZEN = 12;

interface PowerRow {
    power: string | null;
    arrived_packets: number;
    arrived_pieces: number;
    incoming_packets: number;
    incoming_pieces: number;
    remaining_pieces: number;
}

interface ColorRow {
    power: string | null;
    color: string | null;
    arrived_pieces: number;
    incoming_pieces: number;
    remaining_pieces: number;
}

interface ItemDetails extends ItemRow {
    has_powers: boolean;
    quantities_by_power: PowerRow[];
    quantities_by_color: ColorRow[];
}

/** A small table inside a card, for the quantities by power and by color. */
function QuantityTable({ headers, rows }: { headers: string[]; rows: ReactNode[][] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-line bg-subtle/60 text-xs text-muted">
                        {headers.map((header, index) => (
                            <th key={index} className={cn('px-5 py-2.5 font-medium whitespace-nowrap', index === 0 ? 'text-start' : 'text-end')}>
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((cells, rowIndex) => (
                        <tr key={rowIndex} className="border-b border-line last:border-0">
                            {cells.map((cell, index) => (
                                <td key={index} className={cn('px-5 py-2.5 whitespace-nowrap tabular-nums', index === 0 ? 'text-start' : 'text-end')}>
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** The item's page: its cards, its details, what came in of each power and of each color, its description, and its batches. */
export function ItemPage() {
    const t = useT();
    const { id } = useParams();
    const [params, setParams] = useSearchParams();
    const [tab, setTab] = useState(params.get('tab') ?? 'details');
    const { data, error } = useQuery({ queryKey: ['record', 'items', id], queryFn: () => api.get<{ data: ItemDetails; can: { view_cost_price: boolean } }>(`/items/${id}`) });
    const { data: metrics } = useQuery({ queryKey: ['metric', 'item', id], queryFn: () => api.get<{ data: MetricPayload[] }>(`/items/${id}/metrics`) });

    if (error) return <QueryError error={error} />;
    if (!data) return <PageSpinner />;

    const item = data.data;
    const canSeeTheCost = data.can.view_cost_price;
    const power = (value: string | null) => <Badge tone="info">{value ?? t('filament/resources/lens_power.none')}</Badge>;
    const empty = <span className="text-faint">—</span>;
    const packetsAndDozens = (packets: number, pieces: number) =>
        pieces <= 0 ? empty : t('filament/resources/item.by_power.packets_and_dozens', { packets: number(packets), dozens: number(pieces / PIECES_IN_A_DOZEN) });
    const pieces = (value: number) => t('filament/resources/item.by_color.pieces', { pieces: number(value) });
    const piecePrice = (dozenPrice: number | undefined) => <span className="mt-0.5 block text-xs text-muted">{t('filament/resources/item.hints.piece_price', { value: number(Math.round((dozenPrice ?? 0) / PIECES_IN_A_DOZEN), 0) })}</span>;

    const changeTab = (value: string) => {
        setTab(value);
        setParams((current) => {
            const next = new URLSearchParams(current);
            if (value === 'details') next.delete('tab');
            else next.set('tab', value);
            return next;
        }, { replace: true });
    };

    return (
        <>
            <PageHeader
                title={item.name}
                breadcrumbs={[{ label: t('filament/resources/item.plural_label'), to: '/items' }, { label: item.name }]}
                badge={
                    <>
                        {!item.is_active && <Badge>{t('admin/catalog.inactive')}</Badge>}
                        {item.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                    </>
                }
                actions={
                    item.can.update && (
                        <ButtonLink to={`/items/${item.id}/edit`} icon={<Pencil className="size-4" />}>
                            {t('admin.actions.edit')}
                        </ButtonLink>
                    )
                }
            />

            <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                {(metrics?.data ?? Array.from({ length: canSeeTheCost ? 8 : 3 }).map(() => undefined)).map((metric, index) => (
                    <MetricView key={metric?.key ?? index} metric={metric} tone={metric?.key === 'profit_margin' && (metric.value.raw ?? 0) < 0 ? 'danger' : undefined} />
                ))}
            </div>

            <Tabs
                className="mb-4"
                value={tab}
                onChange={changeTab}
                tabs={[
                    { value: 'details', label: t('filament/resources/item.sections.details') },
                    { value: 'batches', label: t('filament/resources/batch.plural_label') },
                ]}
            />

            {tab === 'batches' ? (
                <BatchesTable itemId={item.id} stateKey="batches" />
            ) : (
                <div className="grid gap-6">
                    <Card>
                        <CardHeader icon={<Box className="size-4.5" />} title={t('filament/resources/item.sections.details')} />
                        <CardBody className="flex flex-col gap-6 sm:flex-row">
                            {item.image_url ? (
                                <a href={item.image_url} target="_blank" rel="noreferrer" className="shrink-0">
                                    <img src={item.image_url} alt={item.name} className="size-36 rounded-xl object-cover ring-1 ring-line" />
                                </a>
                            ) : (
                                <ItemThumb url={null} className="size-36 rounded-xl" />
                            )}
                            <div className="min-w-0 flex-1">
                                <DescriptionList
                                    columns={3}
                                    items={[
                                        { label: t('filament/resources/item.fields.name'), value: item.name },
                                        { label: t('filament/resources/item.fields.supplier_id'), value: item.supplier?.name ?? '—' },
                                        { label: t('filament/resources/item.fields.pieces_per_packet'), value: item.pieces_per_packet ? t('filament/resources/item.packet_size', { pieces: item.pieces_per_packet }) : '—' },
                                        {
                                            label: t('filament/resources/item.fields.sale_price'),
                                            value: (
                                                <span className="tabular-nums">
                                                    {number(item.sale_price)}
                                                    {piecePrice(item.sale_price)}
                                                </span>
                                            ),
                                        },
                                        {
                                            label: t('filament/resources/item.fields.cost_price'),
                                            hidden: !canSeeTheCost,
                                            value: (
                                                <span className="tabular-nums">
                                                    {number(item.cost_price)}
                                                    {piecePrice(item.cost_price)}
                                                    <span className="mt-1 block text-xs leading-5 text-faint">{t('filament/resources/item.hints.cost_price')}</span>
                                                </span>
                                            ),
                                        },
                                        {
                                            label: t('filament/resources/item.fields.profit_margin'),
                                            hidden: !canSeeTheCost,
                                            value: <span className={cn('tabular-nums', (item.profit_margin ?? 0) < 0 && 'text-danger')}>{number(item.profit_margin)}</span>,
                                        },
                                        {
                                            label: t('filament/resources/item.fields.is_active'),
                                            value: <Badge tone={item.is_active ? 'success' : 'gray'}>{item.is_active ? t('admin/catalog.active') : t('admin/catalog.inactive')}</Badge>,
                                        },
                                    ]}
                                />
                            </div>
                        </CardBody>
                    </Card>

                    {item.has_powers && (
                        <Card>
                            <CardHeader icon={<Eye className="size-4.5" />} title={t('filament/resources/item.sections.by_power')} description={t('filament/resources/item.sections.by_power_description')} />
                            <QuantityTable
                                headers={[t('filament/resources/item.by_power.power'), t('filament/resources/item.by_power.arrived'), t('filament/resources/item.by_power.incoming'), t('filament/resources/item.by_power.remaining')]}
                                rows={item.quantities_by_power.map((row) => [
                                    power(row.power),
                                    packetsAndDozens(row.arrived_packets, row.arrived_pieces),
                                    packetsAndDozens(row.incoming_packets, row.incoming_pieces),
                                    <span className="font-semibold text-success">{t('filament/resources/item.by_power.dozens', { dozens: number(row.remaining_pieces / PIECES_IN_A_DOZEN) })}</span>,
                                ])}
                            />
                        </Card>
                    )}

                    {item.quantities_by_color.length > 0 && (
                        <Card>
                            <CardHeader icon={<Palette className="size-4.5" />} title={t('filament/resources/item.sections.by_color')} description={t('filament/resources/item.sections.by_color_description')} />
                            <QuantityTable
                                headers={[
                                    t('filament/resources/item.by_color.power'),
                                    t('filament/resources/item.by_color.color'),
                                    t('filament/resources/item.by_color.arrived'),
                                    t('filament/resources/item.by_color.incoming'),
                                    t('filament/resources/item.by_color.remaining'),
                                ]}
                                rows={item.quantities_by_color.map((row) => [
                                    power(row.power),
                                    <span className="font-medium">{row.color ?? '—'}</span>,
                                    row.arrived_pieces > 0 ? pieces(row.arrived_pieces) : empty,
                                    row.incoming_pieces > 0 ? pieces(row.incoming_pieces) : empty,
                                    <span className="font-semibold text-success">{pieces(row.remaining_pieces)}</span>,
                                ])}
                            />
                        </Card>
                    )}

                    <Card>
                        <CardHeader icon={<FileText className="size-4.5" />} title={t('filament/resources/item.fields.description')} />
                        <CardBody>
                            <p className="text-sm leading-6 whitespace-pre-line">{item.description || '—'}</p>
                        </CardBody>
                    </Card>
                </div>
            )}
        </>
    );
}
