import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    ArrowDownRight,
    ArrowUpRight,
    Archive,
    Banknote,
    Box,
    Calculator,
    CircleAlert,
    CircleCheck,
    CircleX,
    Clock,
    DollarSign,
    FileText,
    Grid2x2,
    Info,
    Package,
    Pencil,
    Plus,
    Receipt,
    Scale,
    Store,
    Tag,
    Trash2,
    TriangleAlert,
    Truck,
} from 'lucide-react';
import { api, ApiError } from '../../lib/api';
import { cn, number } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { useSession } from '../../lib/session';
import { Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, Field, Input, Modal, PageSpinner, Textarea, toast, type Tone } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { QueryError } from '../../pages/NotFound';
import { dinars, dollars, errorsUnder, Facts, FileField, toNumber, yuan, type EnumValue, type Fact } from './shared';
import type { PageAbilities, PurchaseInvoice } from './PurchaseInvoicePage';

const DOZEN = 12;
const MIN_DINAR_RATE = 100;
const MAX_DINAR_RATE = 300;
const CBM_DECIMALS = 3;

type EntryUnit = 'packets' | 'dozens';

interface ColorRow {
    key: string;
    id?: number;
    color_id: number | null;
    color_label?: string | null;
    pieces: string;
}

interface GroupRow {
    key: string;
    id?: number;
    lens_power_id: number | null;
    lens_power_label?: string | null;
    boxes_count: string;
}

interface LineState {
    key: string;
    id?: number;
    specific_description_id: number | null;
    description_label?: string | null;
    entry_unit: EntryUnit;
    boxes_count: string;
    sets: string;
    pieces_per_packet: string;
    item_packet: number | null;
    boxes_per_carton: string;
    carton_cbm: string;
    unit_cost: string;
    colors: ColorRow[];
    groups: GroupRow[];
}

interface FormValues extends Record<string, unknown> {
    supplier_id: number | null;
    supplier_label: string | null;
    invoice_category_id: number | null;
    category_label: string | null;
    date: string;
    exchange_rate: string;
    exchange_rate_source: EnumValue | null;
    exchange_rate_source_url: string | null;
    dinar_rate: string;
    cbm_price: string;
    items: LineState[];
    initial_payment: string;
    images: { path: string; url: string }[];
    notes: string;
}

interface CreateDefaults {
    date: string;
    dinar_rate: number;
    exchange_rate: number | null;
    exchange_rate_source: EnumValue;
    exchange_rate_source_url: string | null;
    bank_answered: boolean;
    can_change_exchange_rate: boolean;
    can_view_cost_price: boolean;
}

interface ItemFacts {
    item_id: number;
    pieces_per_packet: number | null;
    available_quantity: number;
    incoming_quantity: number;
    sale_price: number;
    cost_price: number | null;
}

let nextKey = 1;
const newKey = () => `k${nextKey++}`;
const text = (value: number | null | undefined) => (value === null || value === undefined ? '' : String(value));

const emptyLine = (): LineState => ({
    key: newKey(),
    specific_description_id: null,
    entry_unit: 'packets',
    boxes_count: '',
    sets: '',
    pieces_per_packet: '',
    item_packet: null,
    boxes_per_carton: '',
    carton_cbm: '',
    unit_cost: '',
    colors: [],
    groups: [],
});

/** The dozens that packets of a packet make, or empty without both. */
function setsOf(packets: unknown, packet: unknown): string {
    const p = toNumber(packets);
    const size = toNumber(packet);
    return p > 0 && size > 0 ? String(Math.round(((p * size) / DOZEN) * 100) / 100) : '';
}

/** The packets that dozens make in a packet, whole when they are, or null without both. */
function packetsOf(sets: unknown, packet: unknown): number | null {
    const s = toNumber(sets);
    const size = toNumber(packet);
    if (s <= 0 || size <= 0) return null;
    const packets = (s * DOZEN) / size;
    return Math.abs(packets - Math.round(packets)) <= 0.0001 ? Math.round(packets) : Math.round(packets * 100) / 100;
}

/** Writes the quantity that is not typed from the one that is, as the panel's form does. */
function syncQuantity(line: LineState): LineState {
    if (line.entry_unit === 'dozens') {
        const packets = packetsOf(line.sets, line.pieces_per_packet);
        return packets === null ? line : { ...line, boxes_count: String(packets) };
    }
    return { ...line, sets: setsOf(line.boxes_count, line.pieces_per_packet) };
}

const lineTotal = (line: LineState) => Math.round(toNumber(line.boxes_count) * toNumber(line.pieces_per_packet) * toNumber(line.unit_cost) * 100) / 100;
const cartonsOf = (line: LineState) => (line.boxes_count === '' || toNumber(line.boxes_per_carton) <= 0 ? null : Math.ceil(toNumber(line.boxes_count) / toNumber(line.boxes_per_carton)));
const lineCbm = (line: LineState) => (cartonsOf(line) ?? 0) * toNumber(line.carton_cbm);
const dinarsPerDollar = (rate: unknown) => (toNumber(rate) > 0 ? (toNumber(rate) * 1000) / 100 : null);

function useFact() {
    const t = useT();
    return (name: string, value: number | null, tone: Tone, icon?: ReactNode, decimals = 2, replace: Record<string, string> = {}): Fact => ({
        text: t(`filament/resources/purchase_invoice.facts.${name}`, { value: value === null ? '—' : number(value, decimals), ...replace }),
        hint: t(`filament/resources/purchase_invoice.facts_hints.${name}`),
        tone,
        icon,
    });
}

/** What is left as a chip: still left in amber, nothing left in green, too many in red. */
function useLeftFact() {
    const fact = useFact();
    return (left: number, of: 'packets' | 'colors', total?: number): Fact => {
        if (left > 0.0001 && total !== undefined) return fact(`${of}_left_of`, left, 'warning', <CircleAlert className="size-3" />, 2, { total: number(total) });
        if (left > 0.0001) return fact(`${of}_left`, left, 'warning', <CircleAlert className="size-3" />);
        if (left < -0.0001) return fact(`${of}_over`, -left, 'danger', <CircleX className="size-3" />);
        return fact(`${of}_full`, null, 'success', <CircleCheck className="size-3" />);
    };
}

/** A number field with a unit before or after it. */
function UnitInput({ value, onChange, prefix, suffix, disabled, invalid, id, autoFocus }: { value: string; onChange: (value: string) => void; prefix?: string; suffix?: string; disabled?: boolean; invalid?: boolean; id?: string; autoFocus?: boolean }) {
    return (
        <div className="flex items-stretch" dir="ltr">
            {prefix && <span className="flex items-center rounded-s-lg border border-e-0 border-line bg-subtle px-2.5 text-xs whitespace-nowrap text-muted">{prefix}</span>}
            <Input
                id={id}
                autoFocus={autoFocus}
                inputMode="decimal"
                value={value}
                disabled={disabled}
                invalid={invalid}
                onChange={(event) => onChange(event.target.value)}
                className={cn('min-w-0 flex-1', prefix && 'rounded-s-none', suffix && 'rounded-e-none')}
            />
            {suffix && <span className="flex items-center rounded-e-lg border border-s-0 border-line bg-subtle px-2.5 text-xs whitespace-nowrap text-muted">{suffix}</span>}
        </div>
    );
}

/** A small dialog that adds a choice of a select (a category, a color) by its name, then picks it. */
function QuickCreate({ endpoint, title, label, onCreated }: { endpoint: string; title: string; label: string; onCreated: (option: { value: number; label: string }) => void }) {
    const t = useT();
    const client = useQueryClient();
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [error, setError] = useState<string | undefined>();
    const save = useSave((body: { name: string }) => api.post<{ data: { id: number; name: string } }>(endpoint, body), {
        onSuccess: (result) => {
            client.invalidateQueries({ queryKey: ['options'] });
            onCreated({ value: result.data.id, label: result.data.name });
            setOpen(false);
            setName('');
        },
        onError: (failure) => {
            if (failure instanceof ApiError && failure.status === 422) setError(failure.first('name'));
            else toast.error(failure instanceof ApiError ? failure.message : String(failure));
        },
    });
    return (
        <>
            <Button size="sm" variant="ghost" className="h-9.5 shrink-0 px-2.5" icon={<Plus className="size-4" />} onClick={() => setOpen(true)} aria-label={title} title={title} />
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                size="sm"
                title={title}
                footer={
                    <>
                        <Button onClick={() => setOpen(false)}>{t('admin.actions.cancel')}</Button>
                        <Button variant="primary" loading={save.isPending} onClick={() => save.mutate({ name })}>
                            {t('admin.actions.create')}
                        </Button>
                    </>
                }
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        save.mutate({ name });
                    }}
                >
                    <Field label={label} error={error} required>
                        {(id) => <Input id={id} autoFocus value={name} onChange={(event) => setName(event.target.value)} invalid={Boolean(error)} />}
                    </Field>
                </form>
            </Modal>
        </>
    );
}

/** The modal that adds a group of a line (or edits one): its power and its packets, with what is left of the line's packets. */
function GroupModal({ line, group, onSave, onClose }: { line: LineState; group: GroupRow | null; onSave: (group: GroupRow, another: boolean) => void; onClose: () => void }) {
    const t = useT();
    const leftFact = useLeftFact();
    const [powerId, setPowerId] = useState<number | null>(group?.lens_power_id ?? null);
    const [powerLabel, setPowerLabel] = useState<string | null>(group?.lens_power_label ?? null);
    const [packets, setPackets] = useState(group?.boxes_count ?? '');
    const [error, setError] = useState<string | undefined>();
    const total = toNumber(line.boxes_count);
    const left = Math.max(0, total - line.groups.filter((other) => other.key !== group?.key).reduce((sum, other) => sum + toNumber(other.boxes_count), 0));

    const submit = (another: boolean) => {
        const value = toNumber(packets);
        if (total <= 0) return setError(t('invoices.validation.line_quantity_first'));
        if (value <= 0 || !Number.isInteger(value)) return setError(t('invoices.validation.must_be_whole', { attribute: t('filament/resources/invoice_item.fields.boxes_count') }));
        if (value > left + 0.0001) return setError(t('invoices.validation.group_more_than_left', { left: number(left) }));
        onSave({ key: group?.key ?? newKey(), id: group?.id, lens_power_id: powerId, lens_power_label: powerLabel, boxes_count: String(value) }, another);
        if (another) {
            setPowerId(null);
            setPowerLabel(null);
            setPackets('');
            setError(undefined);
        }
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="md"
            title={t(`filament/resources/purchase_invoice.actions.${group ? 'edit_group' : 'add_group'}`)}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    {!group && <Button onClick={() => submit(true)}>{t('filament/resources/purchase_invoice.actions.save_group_and_add_more')}</Button>}
                    <Button variant="primary" onClick={() => submit(false)}>
                        {t('filament/resources/purchase_invoice.actions.save_group')}
                    </Button>
                </>
            }
        >
            <form
                className="grid gap-4 sm:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit(false);
                }}
            >
                <Field label={t('filament/resources/invoice_item.fields.lens_power_id')}>
                    {(id) => (
                        <SearchSelect
                            id={id}
                            endpoint="/purchase-invoices/lens-power-options"
                            value={powerId}
                            initialLabel={powerLabel}
                            placeholder={t('filament/resources/lens_power.none')}
                            onChange={(value, option) => {
                                setPowerId(value === null ? null : Number(value));
                                setPowerLabel(option?.label ?? null);
                            }}
                        />
                    )}
                </Field>
                <Field label={t('filament/resources/invoice_item.fields.boxes_count')} error={error} required hint={<Facts facts={[leftFact(left - toNumber(packets), 'packets', total)]} />}>
                    {(id) => <Input id={id} autoFocus inputMode="numeric" dir="ltr" value={packets} onChange={(event) => setPackets(event.target.value)} invalid={Boolean(error)} />}
                </Field>
            </form>
        </Modal>
    );
}

function LineCard({
    line,
    index,
    values,
    errors,
    canViewCostPrice,
    canRemove,
    onChange,
    onRemove,
}: {
    line: LineState;
    index: number;
    values: FormValues;
    errors: Record<string, string>;
    canViewCostPrice: boolean;
    canRemove: boolean;
    onChange: (line: LineState) => void;
    onRemove: () => void;
}) {
    const t = useT();
    const client = useQueryClient();
    const fact = useFact();
    const leftFact = useLeftFact();
    const [groupModal, setGroupModal] = useState<GroupRow | null | undefined>(undefined);
    const prefix = `items.${index}`;
    const error = (field: string) => errors[`${prefix}.${field}`];
    const field = (name: string) => t(`filament/resources/invoice_item.fields.${name}`);
    const dozens = line.entry_unit === 'dozens';

    const factsQuery = (descriptionId: number) => ({
        queryKey: ['purchases', 'line-item', descriptionId],
        queryFn: () => api.get<ItemFacts>(`/purchase-invoices/line-item/${descriptionId}`),
        staleTime: 60_000,
    });
    const { data: item } = useQuery({ ...factsQuery(line.specific_description_id ?? 0), enabled: line.specific_description_id !== null });

    const update = (changes: Partial<LineState>, sync = false) => {
        const next = { ...line, ...changes };
        onChange(sync ? syncQuantity(next) : next);
    };

    const chooseItem = async (descriptionId: number | null, label: string | null) => {
        if (descriptionId === null) return update({ specific_description_id: null, description_label: null, item_packet: null });
        const facts = await client.fetchQuery(factsQuery(descriptionId)).catch(() => null);
        const packet = facts?.pieces_per_packet ?? null;
        update({ specific_description_id: descriptionId, description_label: label, item_packet: packet, ...(packet !== null ? { pieces_per_packet: String(packet) } : {}) }, packet !== null);
    };

    // The facts under the fields, following the typing, as the panel's form shows them.
    const cartons = cartonsOf(line);
    const cbm = lineCbm(line);
    const quantity = toNumber(line.boxes_count) * toNumber(line.pieces_per_packet);
    const cbmPrice = values.cbm_price;
    const transport = cbm * toNumber(cbmPrice);
    const rate = toNumber(values.exchange_rate);
    const perDollar = dinarsPerDollar(values.dinar_rate);
    const total = lineTotal(line);
    const dozenCost = toNumber(line.unit_cost) * DOZEN;
    const costFacts: Fact[] = [fact('dozen_cost', dozenCost > 0 ? dozenCost : null, 'accent', <Grid2x2 className="size-3" />), fact('line_total', total > 0 ? total : null, 'accent', <Calculator className="size-3" />)];
    let finalUnitCost: number | null = null;
    if (rate > 0 && perDollar !== null) {
        const transportPerPiece = quantity > 0 ? (cbm * toNumber(cbmPrice)) / quantity : 0;
        finalUnitCost = toNumber(line.unit_cost) > 0 ? (toNumber(line.unit_cost) / rate + transportPerPiece) * perDollar : null;
        costFacts.push(fact('final_dozen_cost_iqd', finalUnitCost === null ? null : finalUnitCost * DOZEN, 'accent', <CircleCheck className="size-3" />, 0));
    }
    if (item && canViewCostPrice && item.cost_price !== null) {
        const previous = item.cost_price;
        const current = finalUnitCost === null ? 0 : finalUnitCost * DOZEN;
        const [tone, icon]: [Tone, ReactNode] =
            previous > 0 && current > previous + 0.0001
                ? ['danger', <ArrowUpRight key="up" className="size-3" />]
                : previous > 0 && current > 0 && current < previous - 0.0001
                  ? ['success', <ArrowDownRight key="down" className="size-3" />]
                  : ['gray', <Clock key="clock" className="size-3" />];
        costFacts.push(fact('previous_unit_cost', previous, tone, icon));
    }
    const colorPieces = line.colors.reduce((sum, color) => sum + toNumber(color.pieces), 0);
    const packet = toNumber(line.pieces_per_packet);
    const spread = line.groups.reduce((sum, group) => sum + toNumber(group.boxes_count), 0);
    const packets = toNumber(line.boxes_count);
    const shownFields = /^(boxes_count|sets|pieces_per_packet|boxes_per_carton|carton_cbm|unit_cost|price|specific_description_id|colors|groups)(\.|$)/;
    const lineErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith(`${prefix}.`) && !shownFields.test(key.slice(prefix.length + 1)))
        .map(([, message]) => message);

    return (
        <div className="rounded-xl border border-line bg-surface">
            <div className="flex items-center justify-between gap-2 border-b border-line px-4 py-2.5">
                <span className="text-[13px] font-semibold text-muted">
                    {t('filament/resources/purchase_invoice.fields.item')} {index + 1}
                </span>
                {canRemove && (
                    <Button size="sm" variant="ghost" icon={<Trash2 className="size-4" />} onClick={onRemove} aria-label={t('admin.actions.remove')} className="text-danger hover:text-danger" />
                )}
            </div>
            <div className="space-y-5 p-4">
                <Field
                    label={t('filament/resources/purchase_invoice.fields.item')}
                    error={error('specific_description_id')}
                    required
                    hint={
                        item && (
                            <Facts
                                className="mt-0.5"
                                facts={[
                                    fact('available', item.available_quantity, item.available_quantity > 0 ? 'success' : 'danger', <Box className="size-3" />),
                                    fact('incoming', item.incoming_quantity, item.incoming_quantity > 0 ? 'info' : 'gray', <Truck className="size-3" />),
                                    fact('sale_price', item.sale_price, 'accent', <Tag className="size-3" />),
                                ]}
                            />
                        )
                    }
                >
                    {(id) => (
                        <SearchSelect
                            id={id}
                            endpoint="/purchase-invoices/line-options"
                            value={line.specific_description_id}
                            initialLabel={line.description_label}
                            invalid={Boolean(error('specific_description_id'))}
                            onChange={(value, option) => chooseItem(value === null ? null : Number(value), option?.label ?? null)}
                        />
                    )}
                </Field>

                <fieldset className="rounded-lg border border-line p-3.5">
                    <legend className="px-1.5 text-xs font-semibold text-muted">{t('filament/resources/purchase_invoice.sections.line_packing')}</legend>
                    <div className="mb-3 flex flex-wrap items-center gap-3">
                        <span className="text-[13px] font-medium">{field('entry_unit')}</span>
                        <div className="inline-flex rounded-lg bg-subtle p-1" role="radiogroup">
                            {(['packets', 'dozens'] as EntryUnit[]).map((unit) => (
                                <button
                                    key={unit}
                                    type="button"
                                    role="radio"
                                    aria-checked={line.entry_unit === unit}
                                    onClick={() => update({ entry_unit: unit }, true)}
                                    className={cn('rounded-md px-3 py-1 text-[13px] font-medium', line.entry_unit === unit ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink')}
                                >
                                    {field(unit === 'packets' ? 'boxes_count' : 'sets')}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                        <Field label={field('boxes_count')} error={error('boxes_count')} required={!dozens}>
                            {(id) => <UnitInput id={id} value={line.boxes_count} disabled={dozens} invalid={Boolean(error('boxes_count'))} onChange={(value) => update({ boxes_count: value }, true)} />}
                        </Field>
                        <Field label={field('sets')} error={error('sets')} required={dozens}>
                            {(id) => <UnitInput id={id} value={line.sets} disabled={!dozens} invalid={Boolean(error('sets'))} onChange={(value) => update({ sets: value }, true)} />}
                        </Field>
                        <Field label={field('pieces_per_packet')} error={error('pieces_per_packet')} required>
                            {(id) => (
                                <UnitInput id={id} value={line.pieces_per_packet} disabled={line.item_packet !== null} invalid={Boolean(error('pieces_per_packet'))} onChange={(value) => update({ pieces_per_packet: value }, true)} />
                            )}
                        </Field>
                        <Field label={field('boxes_per_carton')} error={error('boxes_per_carton')} required hint={cartons !== null && <Facts facts={[fact('cartons', cartons, 'info', <Archive className="size-3" />)]} />}>
                            {(id) => <UnitInput id={id} value={line.boxes_per_carton} invalid={Boolean(error('boxes_per_carton'))} onChange={(value) => update({ boxes_per_carton: value })} />}
                        </Field>
                        <Field
                            label={field('carton_cbm')}
                            error={error('carton_cbm')}
                            required
                            className="col-span-2 lg:col-span-1"
                            hint={
                                cbm > 0 && (
                                    <Facts
                                        facts={[
                                            fact('line_cbm', cbm, 'info', <Package className="size-3" />, CBM_DECIMALS),
                                            ...(cbmPrice !== ''
                                                ? [
                                                      fact('transport_cost', transport, 'info', <Truck className="size-3" />),
                                                      fact('transport_cost_per_piece', quantity > 0 ? transport / quantity : null, 'info', <DollarSign className="size-3" />, 4),
                                                  ]
                                                : []),
                                        ]}
                                    />
                                )
                            }
                        >
                            {(id) => <UnitInput id={id} value={line.carton_cbm} invalid={Boolean(error('carton_cbm'))} onChange={(value) => update({ carton_cbm: value })} />}
                        </Field>
                    </div>
                </fieldset>

                <fieldset className="space-y-4 rounded-lg border border-line p-3.5">
                    <legend className="px-1.5 text-xs font-semibold text-muted">{t('filament/resources/purchase_invoice.sections.line_specs')}</legend>
                    <div>
                        <p className="mb-1.5 text-[13px] font-medium">{field('colors')}</p>
                        {line.colors.length > 0 && (
                            <div className="mb-2 space-y-2">
                                {line.colors.map((color, colorIndex) => (
                                    <div key={color.key} className="flex items-start gap-2">
                                        <div className="min-w-0 flex-[3]">
                                            <SearchSelect
                                                endpoint="/options/colors"
                                                value={color.color_id}
                                                initialLabel={color.color_label}
                                                clearable={false}
                                                invalid={Boolean(error(`colors.${colorIndex}.color_id`))}
                                                onChange={(value, option) =>
                                                    update({ colors: line.colors.map((other) => (other.key === color.key ? { ...other, color_id: value === null ? null : Number(value), color_label: option?.label } : other)) })
                                                }
                                            />
                                            {error(`colors.${colorIndex}.color_id`) && <p className="mt-1 text-xs text-danger">{error(`colors.${colorIndex}.color_id`)}</p>}
                                        </div>
                                        <div className="min-w-0 flex-[2]">
                                            <UnitInput
                                                value={color.pieces}
                                                suffix={field('pieces')}
                                                invalid={Boolean(error(`colors.${colorIndex}.pieces`))}
                                                onChange={(value) => update({ colors: line.colors.map((other) => (other.key === color.key ? { ...other, pieces: value } : other)) })}
                                            />
                                        </div>
                                        <Button size="sm" variant="ghost" className="h-9.5" icon={<Trash2 className="size-4" />} aria-label={t('admin.actions.remove')} onClick={() => update({ colors: line.colors.filter((other) => other.key !== color.key) })} />
                                    </div>
                                ))}
                            </div>
                        )}
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                size="sm"
                                icon={<Plus className="size-4" />}
                                disabled={packet > 0 && colorPieces >= packet - 0.0001}
                                onClick={() => update({ colors: [...line.colors, { key: newKey(), color_id: null, pieces: '' }] })}
                            >
                                {t('filament/resources/purchase_invoice.actions.add_color')}
                            </Button>
                            <QuickCreate
                                endpoint="/colors"
                                title={t('admin/purchases.form.new_color')}
                                label={t('filament/resources/color.fields.name')}
                                onCreated={(option) => update({ colors: [...line.colors, { key: newKey(), color_id: option.value, color_label: option.label, pieces: '' }] })}
                            />
                            <Facts facts={[leftFact(packet - colorPieces, 'colors', packet)]} />
                        </div>
                        {error('colors') && <p className="mt-1.5 text-xs text-danger">{error('colors')}</p>}
                    </div>

                    <div>
                        <p className="mb-1.5 text-[13px] font-medium">{field('groups')}</p>
                        {line.groups.length > 0 && (
                            <div className="mb-2 divide-y divide-line rounded-lg border border-line">
                                {line.groups.map((group) => (
                                    <div key={group.key} className="flex items-center justify-between gap-2 px-3 py-2">
                                        <span className={cn('font-semibold', !group.lens_power_id && 'text-muted')}>
                                            {group.lens_power_id ? (group.lens_power_label ?? '—') : t('filament/resources/lens_power.none')}
                                        </span>
                                        <span className="flex items-center gap-1">
                                            <span className="text-sm font-medium">{t('filament/resources/invoice_item.group.packets', { packets: number(group.boxes_count) })}</span>
                                            <Button size="sm" variant="ghost" icon={<Pencil className="size-4" />} aria-label={t('filament/resources/purchase_invoice.actions.edit_group')} onClick={() => setGroupModal(group)} />
                                            <Button size="sm" variant="ghost" icon={<Trash2 className="size-4" />} aria-label={t('admin.actions.remove')} onClick={() => update({ groups: line.groups.filter((other) => other.key !== group.key) })} />
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                        <Button size="sm" icon={<Plus className="size-4" />} onClick={() => setGroupModal(null)}>
                            {t('filament/resources/purchase_invoice.actions.add_group')}
                        </Button>
                        <div className="mt-1.5">
                            {spread <= 0 || packets <= 0 ? (
                                <p className="text-xs text-muted">{t('filament/resources/purchase_invoice.groups_hint')}</p>
                            ) : (
                                <Facts facts={[fact('groups_packets', spread, 'info', <Grid2x2 className="size-3" />, 2, { total: number(packets) }), leftFact(packets - spread, 'packets')]} />
                            )}
                        </div>
                        {error('groups') && <p className="mt-1.5 text-xs text-danger">{error('groups')}</p>}
                    </div>
                </fieldset>

                <fieldset className="rounded-lg border border-line p-3.5">
                    <legend className="px-1.5 text-xs font-semibold text-muted">{t('filament/resources/purchase_invoice.sections.line_cost')}</legend>
                    <Field label={t('filament/resources/purchase_invoice.fields.unit_cost')} error={error('unit_cost') ?? error('price')} required hint={<Facts facts={costFacts} />} className="max-w-xl">
                        {(id) => <UnitInput id={id} value={line.unit_cost} suffix="¥" invalid={Boolean(error('unit_cost') ?? error('price'))} onChange={(value) => update({ unit_cost: value })} />}
                    </Field>
                </fieldset>
                {lineErrors.length > 0 && <p className="text-xs text-danger">{lineErrors.join(' ')}</p>}
            </div>
            {groupModal !== undefined && (
                <GroupModal
                    line={line}
                    group={groupModal}
                    onClose={() => setGroupModal(undefined)}
                    onSave={(group, another) => {
                        const exists = line.groups.some((other) => other.key === group.key);
                        update({ groups: exists ? line.groups.map((other) => (other.key === group.key ? group : other)) : [...line.groups, group] });
                        if (!another) setGroupModal(undefined);
                    }}
                />
            )}
        </div>
    );
}

/** A figure of the amounts section, written large in a colored badge. */
function Figure({ label, value, tone }: { label: string; value: string; tone: 'accent' | 'success' | 'danger' | 'info' }) {
    const tones = { accent: 'bg-accent-soft text-accent', success: 'bg-success-soft text-success', danger: 'bg-danger-soft text-danger', info: 'bg-info-soft text-info' };
    return (
        <div className="min-w-0">
            <p className="text-xs font-medium text-muted">{label}</p>
            <p className={cn('mt-1.5 inline-block rounded-lg px-2.5 py-1 text-base font-semibold whitespace-nowrap tabular-nums', tones[tone])}>{value}</p>
        </div>
    );
}

function toState(invoice: PurchaseInvoice): FormValues {
    return {
        supplier_id: invoice.supplier_id,
        supplier_label: invoice.supplier?.name ?? null,
        invoice_category_id: invoice.invoice_category_id,
        category_label: invoice.category?.name ?? null,
        date: invoice.date ?? '',
        exchange_rate: text(invoice.exchange_rate),
        exchange_rate_source: invoice.exchange_rate_source,
        exchange_rate_source_url: invoice.exchange_rate_source_url,
        dinar_rate: text(invoice.dinar_rate),
        cbm_price: text(invoice.cbm_price),
        initial_payment: '',
        images: invoice.images,
        notes: invoice.notes ?? '',
        items: invoice.items.map((line) =>
            syncQuantity({
                key: newKey(),
                id: line.id,
                specific_description_id: line.specific_description_id,
                description_label: line.description,
                entry_unit: 'packets',
                boxes_count: text(line.boxes_count),
                sets: '',
                pieces_per_packet: text(line.pieces_per_packet),
                item_packet: line.item?.pieces_per_packet ?? null,
                boxes_per_carton: text(line.boxes_per_carton),
                carton_cbm: text(line.carton_cbm),
                unit_cost: text(line.unit_cost),
                colors: line.colors.map((color) => ({ key: newKey(), id: color.id, color_id: color.color_id, color_label: color.color, pieces: text(color.pieces) })),
                groups: line.groups.map((group) => ({ key: newKey(), id: group.id, lens_power_id: group.lens_power_id, lens_power_label: group.lens_power, boxes_count: text(group.boxes_count) })),
            }),
        ),
    };
}

/** What the form sends: the fields the API reads, without the labels it keeps for its selects. */
function toPayload(values: FormValues, creating: boolean) {
    return {
        supplier_id: values.supplier_id,
        invoice_category_id: values.invoice_category_id,
        date: values.date,
        exchange_rate: values.exchange_rate,
        dinar_rate: values.dinar_rate,
        cbm_price: values.cbm_price,
        notes: values.notes || null,
        images: values.images.map((image) => image.path),
        ...(creating ? { initial_payment: values.initial_payment || null } : {}),
        items: values.items.map((line) => ({
            id: line.id,
            specific_description_id: line.specific_description_id,
            entry_unit: line.entry_unit,
            boxes_count: line.boxes_count,
            sets: line.sets,
            pieces_per_packet: line.pieces_per_packet,
            boxes_per_carton: line.boxes_per_carton,
            carton_cbm: line.carton_cbm,
            unit_cost: line.unit_cost,
            colors: line.colors.map((color) => ({ id: color.id, color_id: color.color_id, pieces: color.pieces })),
            groups: line.groups.map((group) => ({ id: group.id, lens_power_id: group.lens_power_id, boxes_count: group.boxes_count })),
        })),
    };
}

function InvoiceForm({ initial, invoiceId, canChangeRate, canViewCostPrice, cbmLocked, paidAmount, draftKey, bankAnswered }: {
    initial: FormValues;
    invoiceId?: number;
    canChangeRate: boolean;
    canViewCostPrice: boolean;
    cbmLocked: boolean;
    paidAmount: number;
    draftKey?: string;
    bankAnswered?: boolean;
}) {
    const t = useT();
    const navigate = useNavigate();
    const creating = invoiceId === undefined;
    const form = useForm<FormValues>(initial);
    const { values, errors } = form;
    const [restored, setRestored] = useState(false);
    const [discarding, setDiscarding] = useState(false);
    const draftLoaded = useRef(false);
    const fact = useFact();
    const field = (name: string) => t(`filament/resources/invoice.fields.${name}`);

    // The copy of the form this browser keeps while a new invoice is typed, brought back when the page opens again (never the images).
    useEffect(() => {
        if (!draftKey || draftLoaded.current) return;
        draftLoaded.current = true;
        try {
            const saved = window.localStorage.getItem(draftKey);
            if (!saved) return;
            const draft = JSON.parse(saved) as Partial<FormValues>;
            form.setValues((current) => ({ ...current, ...draft, images: [], exchange_rate: canChangeRate ? (draft.exchange_rate ?? current.exchange_rate) : current.exchange_rate, exchange_rate_source: current.exchange_rate_source }));
            setRestored(true);
            toast.info(t('filament/resources/purchase_invoice.notifications.draft_restored.title'), t('filament/resources/purchase_invoice.notifications.draft_restored.body'));
        } catch {
            // A copy that cannot be read is ignored.
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draftKey]);

    useEffect(() => {
        if (!draftKey) return;
        const timer = setTimeout(() => {
            try {
                window.localStorage.setItem(draftKey, JSON.stringify({ ...values, images: [] }));
            } catch {
                // Storage may be unavailable; the draft is only a convenience.
            }
        }, 600);
        return () => clearTimeout(timer);
    }, [draftKey, values]);

    const forgetDraft = () => {
        try {
            if (draftKey) window.localStorage.removeItem(draftKey);
        } catch {
            // Nothing to forget.
        }
    };

    const { data: supplierDebt } = useQuery({
        queryKey: ['purchases', 'supplier-debt', values.supplier_id],
        queryFn: () => api.get<{ debt: number }>(`/purchase-invoices/supplier-debt/${values.supplier_id}`).then((response) => response.debt),
        enabled: creating && values.supplier_id !== null,
    });

    const save = useSave((body: ReturnType<typeof toPayload>) => (creating ? api.post<{ data: { id: number } }>('/purchase-invoices', body) : api.put<{ data: { id: number } }>(`/purchase-invoices/${invoiceId}`, body)), {
        invalidate: ['list', 'record', 'metric', 'purchases'],
        success: t('admin.actions.saved'),
        onSuccess: (result) => {
            forgetDraft();
            navigate(`/purchase-invoices/${result.data.id}`);
        },
        onError: form.fail,
    });

    const setLine = (index: number, line: LineState) => form.set('items', values.items.map((other, position) => (position === index ? line : other)));
    const total = values.items.reduce((sum, line) => sum + lineTotal(line), 0);
    const totalCbm = values.items.reduce((sum, line) => sum + lineCbm(line), 0);
    const rate = toNumber(values.exchange_rate);
    const perDollar = dinarsPerDollar(values.dinar_rate);
    const remaining = total - paidAmount;
    const dinarRate = toNumber(values.dinar_rate);
    const dinarRateOk = dinarRate >= MIN_DINAR_RATE && dinarRate <= MAX_DINAR_RATE;
    const initialPayment = toNumber(values.initial_payment);
    const afterPayment = total - initialPayment;
    const generalErrors = errorsUnder(errors, 'items').filter((message) => errors.items === message);

    const sourceHint = values.exchange_rate_source && (
        <span>
            {t('filament/resources/purchase_invoice.exchange_rate_source_hint', { source: '\u0000' }).split('\u0000')[0]}
            {values.exchange_rate_source_url ? (
                <a href={values.exchange_rate_source_url} target="_blank" rel="noopener noreferrer" className="text-accent underline">
                    {values.exchange_rate_source.label} ↗
                </a>
            ) : (
                values.exchange_rate_source.label
            )}
            {t('filament/resources/purchase_invoice.exchange_rate_source_hint', { source: '\u0000' }).split('\u0000')[1]}
        </span>
    );

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                save.mutate(toPayload(values, creating));
            }}
            className="space-y-5"
        >
            {creating && !bankAnswered && (
                <div className="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning-soft p-3.5 text-sm">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" />
                    <div>
                        <p className="font-medium">{t('filament/resources/purchase_invoice.notifications.exchange_rate_unavailable.title')}</p>
                        <p className="text-muted">{t('filament/resources/purchase_invoice.notifications.exchange_rate_unavailable.body')}</p>
                    </div>
                </div>
            )}
            {restored && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-info/30 bg-info-soft p-3.5 text-sm">
                    <span className="flex items-center gap-2">
                        <Info className="size-4 text-info" />
                        {t('filament/resources/purchase_invoice.notifications.draft_restored.title')}
                    </span>
                    <Button size="sm" icon={<Trash2 className="size-4" />} onClick={() => setDiscarding(true)}>
                        {t('filament/resources/purchase_invoice.actions.discard_draft.label')}
                    </Button>
                </div>
            )}

            <Card>
                <CardHeader title={t('filament/resources/purchase_invoice.sections.details')} icon={<FileText className="size-4.5" />} />
                <CardBody className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Field label={field('supplier_id')} error={errors.supplier_id} required>
                        {(id) => (
                            <SearchSelect
                                id={id}
                                endpoint="/options/suppliers"
                                value={values.supplier_id}
                                initialLabel={values.supplier_label}
                                invalid={Boolean(errors.supplier_id)}
                                onChange={(value, option) => {
                                    form.set('supplier_id', value === null ? null : Number(value));
                                    form.set('supplier_label', option?.label ?? null);
                                }}
                            />
                        )}
                    </Field>
                    <Field label={field('invoice_category_id')} error={errors.invoice_category_id} required>
                        {(id) => (
                            <div className="flex gap-1.5">
                                <div className="min-w-0 flex-1">
                                    <SearchSelect
                                        id={id}
                                        endpoint="/options/invoice-categories"
                                        value={values.invoice_category_id}
                                        initialLabel={values.category_label}
                                        invalid={Boolean(errors.invoice_category_id)}
                                        onChange={(value, option) => {
                                            form.set('invoice_category_id', value === null ? null : Number(value));
                                            form.set('category_label', option?.label ?? null);
                                        }}
                                    />
                                </div>
                                <QuickCreate
                                    endpoint="/invoice-categories"
                                    title={t('admin/purchases.form.new_category')}
                                    label={t('filament/resources/invoice_category.fields.name')}
                                    onCreated={(option) => {
                                        form.set('invoice_category_id', option.value);
                                        form.set('category_label', option.label);
                                    }}
                                />
                            </div>
                        )}
                    </Field>
                    <Field label={field('date')} error={errors.date} required>
                        {(id) => <Input id={id} type="date" dir="ltr" value={values.date} onChange={(event) => form.set('date', event.target.value)} invalid={Boolean(errors.date)} />}
                    </Field>
                    <Field label={field('exchange_rate')} error={errors.exchange_rate} required={canChangeRate} hint={sourceHint}>
                        {(id) => (
                            <UnitInput
                                id={id}
                                prefix="1 $ ="
                                suffix="¥"
                                value={values.exchange_rate}
                                disabled={!canChangeRate}
                                invalid={Boolean(errors.exchange_rate)}
                                onChange={(value) => {
                                    form.set('exchange_rate', value);
                                    form.set('exchange_rate_source', { value: 'manual', label: t('filament/resources/invoice.exchange_rate_sources.manual'), color: null });
                                    form.set('exchange_rate_source_url', null);
                                }}
                            />
                        )}
                    </Field>
                    <Field
                        label={field('dinar_rate')}
                        error={errors.dinar_rate}
                        required
                        hint={
                            dinarRate <= 0 ? (
                                t('filament/resources/purchase_invoice.dinar_rate.how_to_type')
                            ) : (
                                <span className="flex flex-wrap items-center gap-1.5">
                                    <Badge tone={dinarRateOk ? 'success' : 'danger'} icon={dinarRateOk ? <CircleCheck className="size-3" /> : <TriangleAlert className="size-3" />}>
                                        {t('filament/resources/purchase_invoice.dinar_rate.per_dollar', { value: number(perDollar) })}
                                    </Badge>
                                    <span className={dinarRateOk ? '' : 'text-danger'}>
                                        {dinarRateOk ? t('filament/resources/purchase_invoice.dinar_rate.per_hundred', { value: number(dinarRate * 1000) }) : t('filament/resources/purchase_invoice.dinar_rate.out_of_range')}
                                    </span>
                                </span>
                            )
                        }
                    >
                        {(id) => <UnitInput id={id} prefix="100 $ =" suffix={t('filament/resources/purchase_invoice.dinar_rate_unit')} value={values.dinar_rate} invalid={Boolean(errors.dinar_rate)} onChange={(value) => form.set('dinar_rate', value)} />}
                    </Field>
                    <Field
                        label={field('cbm_price')}
                        error={errors.cbm_price}
                        required={!cbmLocked}
                        hint={cbmLocked ? t('filament/resources/purchase_invoice.cbm_price_locked_hint') : t('filament/resources/purchase_invoice.cbm_price_hint')}
                    >
                        {(id) => <UnitInput id={id} suffix="$" value={values.cbm_price} disabled={cbmLocked} invalid={Boolean(errors.cbm_price)} onChange={(value) => form.set('cbm_price', value)} />}
                    </Field>
                </CardBody>
            </Card>

            <div className="space-y-3">
                <h2 className="text-[15px] font-semibold">{t('filament/resources/purchase_invoice.fields.items')}</h2>
                {values.items.map((line, index) => (
                    <LineCard
                        key={line.key}
                        line={line}
                        index={index}
                        values={values}
                        errors={errors}
                        canViewCostPrice={canViewCostPrice}
                        canRemove={values.items.length > 1}
                        onChange={(next) => setLine(index, next)}
                        onRemove={() => form.set('items', values.items.filter((_, position) => position !== index))}
                    />
                ))}
                {generalErrors.length > 0 && <p className="text-sm text-danger">{generalErrors.join(' ')}</p>}
                <Button icon={<Plus className="size-4" />} onClick={() => form.set('items', [...values.items, emptyLine()])}>
                    {t('filament/resources/purchase_invoice.actions.add_line')}
                </Button>
            </div>

            <Card>
                <CardHeader title={t('filament/resources/purchase_invoice.sections.amounts')} icon={<Banknote className="size-4.5" />} />
                <CardBody className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <Figure label={field('amount')} value={yuan(total)} tone="accent" />
                    <Figure label={field('amount_usd')} value={rate > 0 ? dollars(total / rate) : '—'} tone="accent" />
                    <Figure label={field('amount_iqd')} value={rate > 0 && perDollar !== null ? dinars((total / rate) * perDollar) : '—'} tone="accent" />
                    <Figure label={field('paid_amount')} value={yuan(paidAmount)} tone="success" />
                    <Figure label={field('remaining')} value={yuan(remaining)} tone={remaining > 0 ? 'danger' : 'success'} />
                    <Figure label={field('cbm')} value={number(totalCbm, CBM_DECIMALS)} tone="info" />
                </CardBody>
            </Card>

            {creating && (
                <Card>
                    <CardBody className="space-y-3">
                        <Field label={t('filament/resources/purchase_invoice.fields.initial_payment')} error={errors.initial_payment} className="max-w-xl">
                            {(id) => <UnitInput id={id} suffix="¥" value={values.initial_payment} invalid={Boolean(errors.initial_payment)} onChange={(value) => form.set('initial_payment', value)} />}
                        </Field>
                        <Facts
                            facts={[
                                ...(supplierDebt !== undefined ? [fact('supplier_debt', supplierDebt, supplierDebt > 0 ? 'danger' : 'success', <Store className="size-3" />)] : []),
                                fact('invoice_amount', total, 'info', <Receipt className="size-3" />),
                                fact('remaining_after_payment', afterPayment, afterPayment < 0 ? 'danger' : afterPayment > 0 ? 'warning' : 'success', <Receipt className="size-3" />),
                                ...(supplierDebt !== undefined
                                    ? [fact('supplier_debt_after', supplierDebt + Math.max(afterPayment, 0), supplierDebt + Math.max(afterPayment, 0) > 0 ? 'danger' : 'success', <Scale className="size-3" />)]
                                    : []),
                            ]}
                        />
                        <div className="flex items-start gap-3 rounded-lg bg-info-soft p-3 text-sm">
                            <Info className="mt-0.5 size-4 shrink-0 text-info" />
                            <div>
                                <p className="font-medium">{t('filament/resources/purchase_invoice.initial_payment_note.heading')}</p>
                                <p className="text-muted">{t('filament/resources/purchase_invoice.initial_payment_note.description')}</p>
                            </div>
                        </div>
                    </CardBody>
                </Card>
            )}

            <Card>
                <CardBody className="grid gap-5 md:grid-cols-2">
                    <Field label={field('images')} error={errorsUnder(errors, 'images')[0]}>
                        {() => <FileField endpoint="/purchase-invoices/uploads" extra={{ kind: 'images' }} accept="image/jpeg,image/png,image/webp" multiple images files={values.images} onChange={(files) => form.set('images', files)} />}
                    </Field>
                    <Field label={field('notes')} error={errors.notes}>
                        {(id) => <Textarea id={id} rows={4} value={values.notes} onChange={(event) => form.set('notes', event.target.value)} />}
                    </Field>
                </CardBody>
            </Card>

            <div className="sticky bottom-0 z-10 -mx-4 flex justify-end gap-2 border-t border-line bg-canvas/90 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border">
                <Button onClick={() => navigate(invoiceId ? `/purchase-invoices/${invoiceId}` : '/purchase-invoices')}>{t('admin.actions.cancel')}</Button>
                <Button type="submit" variant="primary" loading={save.isPending}>
                    {creating ? t('admin.actions.create') : t('admin.actions.save_changes')}
                </Button>
            </div>

            <ConfirmDialog
                open={discarding}
                onClose={() => setDiscarding(false)}
                onConfirm={() => {
                    forgetDraft();
                    setDiscarding(false);
                    setRestored(false);
                    form.setValues(initial);
                }}
                title={t('filament/resources/purchase_invoice.actions.discard_draft.heading')}
                description={t('filament/resources/purchase_invoice.actions.discard_draft.description')}
                confirmLabel={t('filament/resources/purchase_invoice.actions.discard_draft.submit')}
            />
        </form>
    );
}

export function CreatePurchaseInvoicePage() {
    const t = useT();
    const { data: session } = useSession();
    const { data, error, isLoading } = useQuery({ queryKey: ['purchases', 'create-defaults'], queryFn: () => api.get<CreateDefaults>('/purchase-invoices/create'), staleTime: 0, gcTime: 0 });
    const initial = useMemo<FormValues | null>(
        () =>
            data
                ? {
                      supplier_id: null,
                      supplier_label: null,
                      invoice_category_id: null,
                      category_label: null,
                      date: data.date,
                      exchange_rate: text(data.exchange_rate),
                      exchange_rate_source: data.exchange_rate_source,
                      exchange_rate_source_url: data.exchange_rate_source_url,
                      dinar_rate: String(data.dinar_rate),
                      cbm_price: '',
                      items: [emptyLine()],
                      initial_payment: '',
                      images: [],
                      notes: '',
                  }
                : null,
        [data],
    );

    if (isLoading) return <PageSpinner />;
    if (error || !data || !initial) return <QueryError error={error} />;

    return (
        <>
            <PageHeader title={t('admin/purchases.invoices.create_title')} breadcrumbs={[{ label: t('filament/resources/purchase_invoice.plural_label'), to: '/purchase-invoices' }, { label: t('admin.actions.create') }]} />
            <InvoiceForm
                initial={initial}
                canChangeRate={data.can_change_exchange_rate}
                canViewCostPrice={data.can_view_cost_price}
                cbmLocked={false}
                paidAmount={0}
                draftKey={session?.user ? `waqt:purchase-invoice-draft:${session.user.id}` : undefined}
                bankAnswered={data.bank_answered}
            />
        </>
    );
}

export function EditPurchaseInvoicePage() {
    const t = useT();
    const { id } = useParams();
    const { data, error, isLoading } = useQuery({
        queryKey: ['record', '/purchase-invoices', id],
        queryFn: () => api.get<{ data: PurchaseInvoice; can: PageAbilities }>(`/purchase-invoices/${id}`),
    });
    const initial = useMemo(() => (data ? toState(data.data) : null), [data]);

    if (isLoading) return <PageSpinner />;
    if (error || !data || !initial) return <QueryError error={error} />;
    if (!data.data.can.update) return <QueryError error={new ApiError(403, t('admin.errors.forbidden'))} />;

    return (
        <>
            <PageHeader
                title={`${t('admin.actions.edit')}: ${data.data.name}`}
                breadcrumbs={[
                    { label: t('filament/resources/purchase_invoice.plural_label'), to: '/purchase-invoices' },
                    { label: data.data.name, to: `/purchase-invoices/${data.data.id}` },
                    { label: t('admin.actions.edit') },
                ]}
            />
            <InvoiceForm
                initial={initial}
                invoiceId={data.data.id}
                canChangeRate={data.can.edit_exchange_rate}
                canViewCostPrice={data.can.view_cost_price}
                cbmLocked={data.data.cbm_price_locked}
                paidAmount={data.data.paid_amount}
            />
        </>
    );
}
