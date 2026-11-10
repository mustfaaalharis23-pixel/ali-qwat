import { useState, type ReactNode } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { ArrowRightCircle, BadgeCheck, Banknote, Calculator, Clock, Ellipsis, FileText, ListOrdered, Lock, Paperclip, Pencil, Plus, SwatchBook } from 'lucide-react';
import { api } from '../../lib/api';
import { cn, date, dateTime, number } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, Card, CardBody, CardHeader, DescriptionList, Field, Input, Menu, Modal, MoneyInput, PageSpinner, toneOf } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { useRecordActions, type Abilities } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { dinars, dollars, EnumBadge, FileField, RouteButton, SplitBadge, yuan, type EnumValue, type PurchaseInvoiceRow } from './shared';
import { useInvoiceActions } from './invoiceActions';
import { AuditsSection } from '../system/AuditsPanel';

export interface PurchaseLine {
    id: number;
    specific_description_id: number;
    description: string | null;
    item: { id: number; name: string; image_url: string | null; pieces_per_packet: number | null } | null;
    entry_unit: string;
    boxes_count: number | null;
    pieces_per_packet: number | null;
    boxes_per_carton: number | null;
    carton_cbm: number | null;
    unit_cost: number;
    quantity: number;
    cartons_count: number | null;
    cbm: number;
    price: number;
    unit_cost_usd: number | null;
    price_usd: number | null;
    transport_cost_usd: number | null;
    transport_cost_per_piece_usd: number | null;
    unit_cost_iqd: number | null;
    price_iqd: number | null;
    transport_cost_iqd: number | null;
    transport_cost_per_piece_iqd: number | null;
    final_unit_cost_iqd: number | null;
    groups: { id: number; lens_power_id: number | null; lens_power: string | null; boxes_count: number }[];
    colors: { id: number; color_id: number; color: string | null; pieces: number }[];
    colors_description: string | null;
}

export interface PurchaseInvoice extends PurchaseInvoiceRow {
    exchange_rate: number | null;
    exchange_rate_source: EnumValue | null;
    exchange_rate_source_url: string | null;
    dinar_rate: number | null;
    dinars_per_dollar: number | null;
    cbm_price: number | null;
    cbm_price_locked: boolean;
    cbm: number;
    amount_usd: number | null;
    amount_iqd: number | null;
    transport_cost_usd: number | null;
    transport_cost_iqd: number | null;
    total_iqd: number | null;
    importer_checker: string | null;
    importer_confirmed_at: string | null;
    receiver_checker: string | null;
    receiver_confirmed_at: string | null;
    split_from: { id: number; name: string } | null;
    images: { path: string; url: string }[];
    notes: string | null;
    items: PurchaseLine[];
    has_payoffs: boolean;
    has_any_payoffs: boolean;
}

export interface PageAbilities {
    edit_exchange_rate: boolean;
    view_containers: boolean;
    view_items: boolean;
    view_payoffs: boolean;
    create_payoffs: boolean;
    view_cost_price: boolean;
    create_options: InlineCreateAbilities;
}

/** Which choices of the invoice form the user may add from inside it. */
export interface InlineCreateAbilities {
    colors: boolean;
    invoice_categories: boolean;
    lens_powers: boolean;
}

const DOZEN = 12;
const perDozen = (value: number | null | undefined) => (value === null || value === undefined ? null : value * DOZEN);

/** A large number in a soft colored box, as the panel's amount badges. */
function BigFigure({ label, value, tone = 'accent', below }: { label: ReactNode; value: ReactNode; tone?: 'accent' | 'success' | 'danger' | 'info'; below?: ReactNode }) {
    const tones = { accent: 'bg-accent-soft text-accent', success: 'bg-success-soft text-success', danger: 'bg-danger-soft text-danger', info: 'bg-info-soft text-info' };
    return (
        <div className="min-w-0">
            <p className="text-xs font-medium text-muted">{label}</p>
            <p className={cn('mt-1.5 inline-block rounded-lg px-2.5 py-1 text-lg font-semibold tabular-nums', tones[tone])}>{value}</p>
            {below && <div className="mt-1 text-xs text-muted">{below}</div>}
        </div>
    );
}

/** A formula of numbers kept left to right, so it does not read backwards in Arabic. */
const Ltr = ({ children }: { children: ReactNode }) => (
    <span dir="ltr" className="inline-block tabular-nums">
        {children}
    </span>
);

function LineGroupsModal({ line, onClose }: { line: PurchaseLine; onClose: () => void }) {
    const t = useT();
    const packet = line.pieces_per_packet ?? 0;
    const key = (name: string, value: number | string) => t(`filament/resources/purchase_invoice.groups_modal.${name}`, { value: typeof value === 'number' ? number(value) : value });
    return (
        <Modal
            open
            onClose={onClose}
            size="lg"
            title={line.description}
            description={t('filament/resources/purchase_invoice.groups_modal.description', { packet: number(packet), packets: number(line.boxes_count), pieces: number(line.quantity) })}
            footer={<Button onClick={onClose}>{t('filament/resources/purchase_invoice.actions.close')}</Button>}
        >
            <div className="space-y-5">
                {line.colors.length > 0 && (
                    <div className="space-y-2.5">
                        <p className="text-[13px] font-medium">{t('filament/resources/invoice_item.fields.colors')}</p>
                        {line.colors.map((color) => (
                            <div key={color.id} className="space-y-1">
                                <div className="flex justify-between text-sm">
                                    <span className="font-medium">{color.color}</span>
                                    <span className="text-muted">
                                        {key('per_packet', color.pieces)} · {key('pieces', color.pieces * (line.boxes_count ?? 0))}
                                    </span>
                                </div>
                                <div className="h-1.5 overflow-hidden rounded-full bg-subtle">
                                    <div className="h-full rounded-full bg-accent" style={{ width: `${packet > 0 ? Math.min(100, (color.pieces / packet) * 100) : 0}%` }} />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {line.groups.length > 0 && (
                    <div className="grid gap-2 sm:grid-cols-2">
                        {line.groups.map((group) => {
                            const pieces = group.boxes_count * packet;
                            return (
                                <div key={group.id} className="rounded-lg border border-line p-3">
                                    <p className={cn('font-semibold', !group.lens_power && 'text-muted')}>
                                        {group.lens_power ? t('filament/resources/invoice_item.group.power', { value: group.lens_power }) : t('filament/resources/lens_power.none')}
                                    </p>
                                    <p className="mt-1 text-sm text-muted">
                                        {key('packets', group.boxes_count)} · {key('pieces', pieces)} · {key('dozens', pieces / DOZEN)}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </Modal>
    );
}

function FinalCostModal({ line, invoice, onClose }: { line: PurchaseLine; invoice: PurchaseInvoice; onClose: () => void }) {
    const t = useT();
    const label = (name: string, replace = {}) => t(`filament/resources/purchase_invoice.final_cost_modal.${name}`, replace);
    const rate = invoice.exchange_rate ?? 0;
    const perDollar = invoice.dinars_per_dollar ?? 0;
    const y = (value: number | null) => `${number(value ?? 0, 4)} ¥`;
    const d = (value: number | null) => `${number(value ?? 0, 4)} $`;
    const hasTransport = line.transport_cost_usd !== null;
    const goodsDollars = perDozen(line.unit_cost_usd) ?? 0;
    const transportDollars = perDozen(line.transport_cost_per_piece_usd) ?? 0;

    const sections = [
        {
            title: label('goods'),
            tone: 'border-warning/40 bg-warning-soft/40',
            steps: [
                { label: label('dozen_in_yuan'), math: `${y(line.unit_cost)} × ${DOZEN}`, value: y(perDozen(line.unit_cost)) },
                { label: label('in_dollars'), math: `${y(perDozen(line.unit_cost))} ÷ ${number(rate, 4)}`, value: d(goodsDollars) },
                { label: label('in_dinars'), math: `${d(goodsDollars)} × ${number(perDollar)}`, value: dinars(perDozen(line.unit_cost_iqd)) },
            ],
        },
        ...(hasTransport
            ? [
                  {
                      title: label('transport'),
                      tone: 'border-info/30 bg-info-soft/40',
                      steps: [
                          {
                              label: t('filament/resources/invoice_item.fields.cbm'),
                              math: line.cartons_count === null || line.carton_cbm === null ? null : `${label('cartons', { value: number(line.cartons_count) })} × ${number(line.carton_cbm, 4)}`,
                              value: `${number(line.cbm, 4)} CBM`,
                          },
                          { label: label('line_transport'), math: `${number(line.cbm, 4)} CBM × ${d(invoice.cbm_price)}`, value: d(line.transport_cost_usd) },
                          { label: label('dozen_transport'), math: `${d(line.transport_cost_usd)} ÷ ${label('pieces', { value: number(line.quantity) })} × ${DOZEN}`, value: d(transportDollars) },
                          { label: label('in_dinars'), math: `${d(transportDollars)} × ${number(perDollar)}`, value: dinars(perDozen(line.transport_cost_per_piece_iqd)) },
                      ],
                  },
              ]
            : []),
    ];

    return (
        <Modal open onClose={onClose} size="xl" title={line.description} description={label('description')} footer={<Button onClick={onClose}>{t('filament/resources/purchase_invoice.actions.close')}</Button>}>
            <div className="space-y-4">
                <div>
                    <p className="mb-2 text-[13px] font-medium">{label('rates')}</p>
                    <div className="grid gap-2 sm:grid-cols-3">
                        <div className="rounded-lg border border-line p-3">
                            <p className="text-xs text-muted">{t('filament/resources/invoice.fields.exchange_rate')}</p>
                            <p className="mt-1 font-semibold">
                                <Ltr>{t('filament/resources/purchase_invoice.costs_below.rate', { rate: number(rate, 4) })}</Ltr>
                            </p>
                            <p className="text-xs text-muted">{invoice.exchange_rate_source?.label}</p>
                        </div>
                        <div className="rounded-lg border border-line p-3">
                            <p className="text-xs text-muted">{t('filament/resources/invoice.fields.dinar_rate')}</p>
                            <p className="mt-1 font-semibold">
                                <Ltr>1 $ = {dinars(perDollar)}</Ltr>
                            </p>
                            <p className="text-xs text-muted">{label('dinar_rate', { value: number(invoice.dinar_rate) })}</p>
                        </div>
                        {hasTransport && (
                            <div className="rounded-lg border border-line p-3">
                                <p className="text-xs text-muted">{t('filament/resources/invoice.fields.cbm_price')}</p>
                                <p className="mt-1 font-semibold">
                                    <Ltr>{d(invoice.cbm_price)}</Ltr>
                                </p>
                            </div>
                        )}
                    </div>
                </div>
                {sections.map((section) => (
                    <div key={section.title} className={cn('rounded-xl border p-3.5', section.tone)}>
                        <p className="mb-2 text-[13px] font-semibold">{section.title}</p>
                        <div className="divide-y divide-line/70">
                            {section.steps.map((step) => (
                                <div key={step.label} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                    <span>{step.label}</span>
                                    <span className="flex items-center gap-3">
                                        {step.math && (
                                            <span className="text-xs text-muted">
                                                <Ltr>{step.math}</Ltr>
                                            </span>
                                        )}
                                        <span className="font-medium">
                                            <Ltr>{step.value}</Ltr>
                                        </span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
                {!hasTransport && <p className="text-sm text-muted">{label('no_transport')}</p>}
                <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-success-soft p-3.5">
                    <span className="text-sm font-medium">{t('filament/resources/purchase_invoice.fields.final_dozen_cost')}</span>
                    <span className="text-lg font-semibold text-success">
                        <Ltr>{dinars(perDozen(line.final_unit_cost_iqd))}</Ltr>
                    </span>
                </div>
            </div>
        </Modal>
    );
}

function LinesTable({ invoice, canViewItems }: { invoice: PurchaseInvoice; canViewItems: boolean }) {
    const t = useT();
    const [groupsOf, setGroupsOf] = useState<PurchaseLine | null>(null);
    const [costOf, setCostOf] = useState<PurchaseLine | null>(null);
    const below = (name: string, replace: Record<string, string>) => <p className="mt-0.5 text-xs whitespace-nowrap text-muted">{t(`filament/resources/purchase_invoice.lines_below.${name}`, replace)}</p>;
    const head = (key: string) => <th className="px-3 py-2.5 text-start text-xs font-medium whitespace-nowrap text-muted">{t(key)}</th>;

    return (
        <>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="border-b border-line bg-subtle/60">
                        <tr>
                            {head('filament/resources/purchase_invoice.fields.item')}
                            {head('filament/resources/invoice_item.fields.quantity')}
                            {head('filament/resources/invoice_item.fields.cartons_count')}
                            {head('filament/resources/invoice_item.fields.cbm')}
                            {head('filament/resources/purchase_invoice.fields.dozen_cost')}
                            {head('filament/resources/purchase_invoice.fields.line_total')}
                            {head('filament/resources/invoice.fields.transport_cost')}
                            {head('filament/resources/purchase_invoice.fields.final_dozen_cost')}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {invoice.items.map((line) => (
                            <tr key={line.id} className="align-top">
                                <td className="min-w-64 px-3 py-3">
                                    <div className="flex items-start gap-3">
                                        {line.item?.image_url ? (
                                            <a href={line.item.image_url} target="_blank" rel="noreferrer" className="shrink-0">
                                                <img src={line.item.image_url} alt="" className="size-14 rounded-lg border border-line object-cover" />
                                            </a>
                                        ) : (
                                            <div className="size-14 shrink-0 rounded-lg bg-subtle" />
                                        )}
                                        <div className="min-w-0 space-y-1.5">
                                            {canViewItems && line.item ? (
                                                <Link to={`/items/${line.item.id}`} target="_blank" className="font-medium text-accent hover:underline">
                                                    {line.description}
                                                </Link>
                                            ) : (
                                                <p className="font-medium">{line.description}</p>
                                            )}
                                            {(line.groups.length > 0 || line.colors_description) && (
                                                <div className="flex flex-wrap gap-1">
                                                    {line.groups.map((group) => (
                                                        <Badge key={group.id} tone="info">
                                                            {group.lens_power ? t('filament/resources/invoice_item.group.power', { value: group.lens_power }) : t('filament/resources/lens_power.none')} ·{' '}
                                                            {t('filament/resources/invoice_item.group.packets', { packets: number(group.boxes_count) })}
                                                        </Badge>
                                                    ))}
                                                    {line.colors_description && <Badge icon={<SwatchBook className="size-3" />}>{line.colors_description}</Badge>}
                                                    <button type="button" onClick={() => setGroupsOf(line)} className="text-xs font-medium text-accent hover:underline">
                                                        {t('filament/resources/purchase_invoice.actions.show_groups')}
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap">
                                    <span className="font-medium tabular-nums">{number(line.quantity)}</span>
                                    {below('packets', { packets: number(line.boxes_count), capacity: number(line.pieces_per_packet) })}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap">
                                    <span className="tabular-nums">{number(line.cartons_count)}</span>
                                    {line.boxes_per_carton !== null && below('per_carton', { value: number(line.boxes_per_carton) })}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-info">
                                    <span className="tabular-nums">{number(line.cbm, 3)}</span>
                                    {line.carton_cbm !== null && below('carton_cbm', { value: number(line.carton_cbm, 4) })}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap">
                                    <span className="tabular-nums">{yuan(perDozen(line.unit_cost))}</span>
                                    {line.unit_cost_usd !== null && below('usd', { value: number(perDozen(line.unit_cost_usd)) })}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap">
                                    <span className="font-semibold tabular-nums">{yuan(line.price)}</span>
                                    {line.price_usd !== null && below('usd', { value: number(line.price_usd) })}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-info">
                                    <span className="tabular-nums">{dollars(line.transport_cost_usd)}</span>
                                    {line.transport_cost_per_piece_usd !== null && below('per_dozen', { value: number(perDozen(line.transport_cost_per_piece_usd), 3) })}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap">
                                    <span className="font-semibold text-success tabular-nums">{dinars(perDozen(line.final_unit_cost_iqd))}</span>
                                    {line.final_unit_cost_iqd !== null && (
                                        <>
                                            {below('final_parts', { cost: number(perDozen(line.unit_cost_iqd), 0), transport: number(perDozen(line.transport_cost_per_piece_iqd), 0) })}
                                            <button type="button" onClick={() => setCostOf(line)} className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-accent hover:underline">
                                                <Calculator className="size-3.5" />
                                                {t('filament/resources/purchase_invoice.actions.show_final_cost')}
                                            </button>
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {groupsOf && <LineGroupsModal line={groupsOf} onClose={() => setGroupsOf(null)} />}
            {costOf && <FinalCostModal line={costOf} invoice={invoice} onClose={() => setCostOf(null)} />}
        </>
    );
}

interface Payoff {
    id: number;
    amount: number;
    date: string | null;
    image: string | null;
    image_url: string | null;
    is_initial_payment: boolean;
    deleted_at: string | null;
    can: Abilities;
}

function PayoffForm({ invoiceId, payoff, remaining, onClose }: { invoiceId: number; payoff: Payoff | null; remaining: number; onClose: () => void }) {
    const t = useT();
    const form = useForm({
        amount: payoff ? String(payoff.amount) : '',
        date: payoff?.date ?? new Date().toISOString().slice(0, 10),
        image: payoff?.image && payoff.image_url ? [{ path: payoff.image, url: payoff.image_url }] : ([] as { path: string; url: string }[]),
    });
    const save = useSave(
        (values: typeof form.values) => {
            const body = { amount: values.amount, date: values.date, image: values.image[0]?.path ?? null };
            return payoff ? api.put(`/payoffs/${payoff.id}`, body) : api.post(`/purchase-invoices/${invoiceId}/payoffs`, body);
        },
        { invalidate: ['list', 'record', 'metric'], success: t('admin.actions.saved'), onSuccess: onClose, onError: form.fail },
    );
    const max = Math.round((remaining + (payoff?.amount ?? 0)) * 100) / 100;

    return (
        <Modal
            open
            onClose={onClose}
            title={payoff ? t('admin.actions.edit') : t('filament/resources/payoff.label')}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={save.isPending} onClick={() => save.mutate(form.values)}>
                        {t('admin.actions.save')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label={t('filament/resources/payoff.fields.amount')} error={form.errors.amount} hint={`≤ ${yuan(max)}`} required>
                    {(id) => <MoneyInput id={id} value={form.values.amount} onChange={(value) => form.set('amount', value)} invalid={Boolean(form.errors.amount)} />}
                </Field>
                <Field label={t('filament/resources/payoff.fields.date')} error={form.errors.date} required>
                    {(id) => <Input id={id} type="date" dir="ltr" value={form.values.date} onChange={(event) => form.set('date', event.target.value)} invalid={Boolean(form.errors.date)} />}
                </Field>
                <Field label={t('filament/resources/payoff.fields.image')} error={form.errors.image} className="sm:col-span-2">
                    {() => <FileField endpoint="/purchase-invoices/uploads" extra={{ kind: 'payoff' }} accept="image/jpeg,image/png,image/webp" images files={form.values.image} onChange={(files) => form.set('image', files)} />}
                </Field>
            </div>
        </Modal>
    );
}

function PayoffsCard({ invoice }: { invoice: PurchaseInvoice }) {
    const t = useT();
    const [editing, setEditing] = useState<Payoff | null | undefined>(undefined);
    const actions = useRecordActions<Payoff>('/payoffs', { label: (payoff) => yuan(payoff.amount) });
    const endpoint = `/purchase-invoices/${invoice.id}/payoffs`;

    return (
        <Card>
            <CardHeader title={t('filament/resources/payoff.plural_label')} icon={<Banknote className="size-4.5" />} />
            <DataTable<Payoff>
                endpoint={endpoint}
                stateKey="payoffs"
                searchable={false}
                trashed
                className="rounded-none border-0 shadow-none"
                defaultSort={{ column: 'date', direction: 'desc' }}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <Button size="sm" variant="primary" icon={<Plus className="size-4" />} onClick={() => setEditing(null)}>
                            {t('admin.actions.new')}
                        </Button>
                    )
                }
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (payoff) => <span className="text-muted tabular-nums">{payoff.id}</span> },
                    { key: 'date', header: t('filament/resources/payoff.fields.date'), sortable: true, cell: (payoff) => <span className="tabular-nums">{date(payoff.date)}</span> },
                    {
                        key: 'amount',
                        header: t('filament/resources/payoff.fields.amount'),
                        sortable: true,
                        cell: (payoff) => (
                            <div>
                                <span className="font-medium tabular-nums">{yuan(payoff.amount)}</span>
                                {payoff.is_initial_payment && <p className="text-xs text-muted">{t('filament/resources/payoff.fields.is_initial_payment')}</p>}
                                {payoff.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </div>
                        ),
                    },
                    {
                        key: 'image',
                        header: t('filament/resources/payoff.fields.image'),
                        cell: (payoff) =>
                            payoff.image_url ? (
                                <a href={payoff.image_url} target="_blank" rel="noreferrer">
                                    <img src={payoff.image_url} alt="" className="size-9 rounded-md border border-line object-cover" />
                                </a>
                            ) : (
                                <span className="text-faint">—</span>
                            ),
                    },
                ]}
                rowActions={(payoff) => actions.items(payoff, { edit: () => setEditing(payoff) })}
            />
            {editing !== undefined && <PayoffForm invoiceId={invoice.id} payoff={editing} remaining={invoice.remaining} onClose={() => setEditing(undefined)} />}
            {actions.dialogs}
        </Card>
    );
}

export function PurchaseInvoicePage() {
    const t = useT();
    const navigate = useNavigate();
    const { id } = useParams();
    const { data, error, isLoading } = useQuery({
        queryKey: ['record', '/purchase-invoices', id],
        queryFn: () => api.get<{ data: PurchaseInvoice; can: PageAbilities }>(`/purchase-invoices/${id}`),
    });
    const actions = useInvoiceActions({ onDeleted: () => navigate('/purchase-invoices') });

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;

    const invoice = data.data;
    const can = data.can;
    const field = (name: string) => t(`filament/resources/invoice.fields.${name}`);
    const { advance: advanceItem, confirm: confirmItem } = actions.steps(invoice);
    const moreItems = actions.more(invoice);

    return (
        <>
            <PageHeader
                title={invoice.name}
                breadcrumbs={[{ label: t('filament/resources/purchase_invoice.plural_label'), to: '/purchase-invoices' }, { label: `#${invoice.id}` }]}
                badge={
                    <>
                        <EnumBadge value={invoice.status} />
                        {invoice.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                    </>
                }
                actions={
                    <>
                        {!advanceItem.hidden && (
                            <Button variant="primary" icon={<ArrowRightCircle className="size-4" />} onClick={advanceItem.onSelect}>
                                {advanceItem.label}
                            </Button>
                        )}
                        {!confirmItem.hidden && (
                            <Button variant="success" icon={<BadgeCheck className="size-4" />} onClick={confirmItem.onSelect}>
                                {confirmItem.label}
                            </Button>
                        )}
                        {invoice.can.update && (
                            <RouteButton to={`/purchase-invoices/${invoice.id}/edit`} icon={<Pencil className="size-4" />}>
                                {t('admin.actions.edit')}
                            </RouteButton>
                        )}
                        <Menu
                            items={moreItems}
                            trigger={(toggle) => (
                                <Button icon={<Ellipsis className="size-4" />} onClick={toggle}>
                                    {t('filament/resources/purchase_invoice.actions.more')}
                                </Button>
                            )}
                        />
                    </>
                }
            />

            <div className="grid gap-5 lg:grid-cols-3">
                <div className="space-y-5 lg:col-span-2">
                    <Card>
                        <CardHeader title={t('filament/resources/purchase_invoice.sections.details')} icon={<FileText className="size-4.5" />} actions={<SplitBadge split={invoice.split} />} />
                        <CardBody>
                            <DescriptionList
                                items={[
                                    { label: field('supplier_id'), value: invoice.supplier ? <span className="font-medium">{invoice.supplier.name}</span> : '—' },
                                    { label: field('invoice_category_id'), value: invoice.category ? <Badge>{invoice.category.name}</Badge> : '—' },
                                    {
                                        label: field('container_id'),
                                        value: invoice.container ? (
                                            can.view_containers ? (
                                                <Link to={`/containers/${invoice.container.id}`} className="text-accent hover:underline" dir="ltr">
                                                    {invoice.container.name}
                                                </Link>
                                            ) : (
                                                <span dir="ltr">{invoice.container.name}</span>
                                            )
                                        ) : (
                                            '—'
                                        ),
                                    },
                                    { label: field('date'), value: <span className="tabular-nums">{date(invoice.date)}</span> },
                                    { label: field('user_id'), value: invoice.user },
                                    {
                                        label: field('split_from_invoice_id'),
                                        hidden: !invoice.split_from,
                                        value: invoice.split_from && (
                                            <Link to={`/purchase-invoices/${invoice.split_from.id}`} className="text-accent hover:underline">
                                                {invoice.split_from.name}
                                            </Link>
                                        ),
                                    },
                                ]}
                            />
                        </CardBody>
                    </Card>
                    <Card>
                        <CardHeader title={t('filament/resources/purchase_invoice.sections.amounts')} icon={<Banknote className="size-4.5" />} />
                        <CardBody className="grid gap-4 sm:grid-cols-3">
                            <BigFigure label={field('amount')} value={yuan(invoice.amount)} />
                            <BigFigure label={field('paid_amount')} value={yuan(invoice.paid_amount)} tone="success" />
                            <BigFigure label={field('remaining')} value={yuan(invoice.remaining)} tone={invoice.remaining > 0 ? 'danger' : 'success'} />
                        </CardBody>
                    </Card>
                    <Card>
                        <CardHeader title={t('filament/resources/purchase_invoice.sections.costs')} icon={<Calculator className="size-4.5" />} />
                        <CardBody className="grid gap-4 sm:grid-cols-3">
                            <BigFigure
                                label={field('amount_usd')}
                                value={dollars(invoice.amount_usd)}
                                below={invoice.exchange_rate !== null && <Ltr>{t('filament/resources/purchase_invoice.costs_below.rate', { rate: number(invoice.exchange_rate, 5) })}</Ltr>}
                            />
                            <BigFigure
                                label={field('transport_cost')}
                                value={dollars(invoice.transport_cost_usd)}
                                tone="info"
                                below={
                                    invoice.cbm_price !== null && (
                                        <span className="inline-flex items-center gap-1">
                                            {invoice.cbm_price_locked && <Lock className="size-3" />}
                                            <Ltr>{t('filament/resources/purchase_invoice.costs_below.shipping', { cbm: number(invoice.cbm, 3), price: number(invoice.cbm_price) })}</Ltr>
                                        </span>
                                    )
                                }
                            />
                            <BigFigure
                                label={t('filament/resources/purchase_invoice.fields.total_iqd')}
                                value={dinars(invoice.total_iqd)}
                                tone="success"
                                below={
                                    invoice.amount_iqd !== null &&
                                    t('filament/resources/purchase_invoice.costs_below.dinars', { amount: number(invoice.amount_iqd, 0), transport: number(invoice.transport_cost_iqd, 0) })
                                }
                            />
                        </CardBody>
                    </Card>
                </div>
                <Card className="self-start">
                    <CardHeader title={t('filament/resources/purchase_invoice.sections.progress')} icon={<BadgeCheck className="size-4.5" />} />
                    <CardBody className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs font-medium text-muted">{field('status')}</p>
                                <div className="mt-1">
                                    <EnumBadge value={invoice.status} />
                                </div>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted">{field('stage')}</p>
                                <div className="mt-1">
                                    <EnumBadge value={invoice.stage} />
                                </div>
                            </div>
                        </div>
                        {invoice.awaited_confirmation && (
                            <div>
                                <p className="text-xs font-medium text-muted">{field('awaited_confirmation')}</p>
                                <div className="mt-1">
                                    <Badge tone={toneOf('warning')} icon={<Clock className="size-3" />}>
                                        {invoice.awaited_confirmation.label}
                                    </Badge>
                                </div>
                            </div>
                        )}
                        {[
                            { label: field('importer_checker_id'), name: invoice.importer_checker, at: invoice.importer_confirmed_at },
                            { label: field('receiver_checker_id'), name: invoice.receiver_checker, at: invoice.receiver_confirmed_at },
                        ].map((side) => (
                            <div key={side.label}>
                                <p className="text-xs font-medium text-muted">{side.label}</p>
                                <p className="mt-1 flex items-center gap-1.5 text-sm">
                                    {side.name ? <BadgeCheck className="size-4 text-success" /> : null}
                                    {side.name ?? '—'}
                                </p>
                                {side.at && <p className="text-xs text-muted tabular-nums">{dateTime(side.at)}</p>}
                            </div>
                        ))}
                    </CardBody>
                </Card>
            </div>

            <Card className="mt-5">
                <CardHeader title={t('filament/resources/purchase_invoice.fields.items')} description={t('filament/resources/purchase_invoice.lines_description')} icon={<ListOrdered className="size-4.5" />} />
                <LinesTable invoice={invoice} canViewItems={can.view_items} />
            </Card>

            {(invoice.images.length > 0 || invoice.notes) && (
                <Card className="mt-5">
                    <CardHeader title={t('filament/resources/purchase_invoice.sections.attachments')} icon={<Paperclip className="size-4.5" />} />
                    <CardBody className="grid gap-5 md:grid-cols-2">
                        <div>
                            <p className="mb-2 text-xs font-medium text-muted">{field('images')}</p>
                            {invoice.images.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {invoice.images.map((image) => (
                                        <a key={image.path} href={image.url} target="_blank" rel="noreferrer">
                                            <img src={image.url} alt="" className="size-24 rounded-lg border border-line object-cover" />
                                        </a>
                                    ))}
                                </div>
                            ) : (
                                '—'
                            )}
                        </div>
                        <div>
                            <p className="mb-2 text-xs font-medium text-muted">{field('notes')}</p>
                            <p className="text-sm whitespace-pre-line">{invoice.notes ?? '—'}</p>
                        </div>
                    </CardBody>
                </Card>
            )}

            {can.view_payoffs && (
                <div className="mt-5">
                    <PayoffsCard invoice={invoice} />
                </div>
            )}
            <AuditsSection subjectType="invoice" subjectId={invoice.id} />
            {actions.dialogs}
        </>
    );
}
