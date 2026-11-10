import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Image as ImageIcon, Minus, Package, Plus, Search, ShoppingCart, Trash2, X } from 'lucide-react';
import { api } from '../../lib/api';
import { cn } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, Card, CardBody, CardHeader, Empty, Field, Input, MoneyInput, PageSpinner, Select, Skeleton, Textarea, toast } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { QueryError } from '../../pages/NotFound';
import type { CartLineInput, CartSummary, ItemCard } from './types';

interface EditData {
    id: number;
    customer_id: number;
    customer_name: string | null;
    payment_type: string;
    date: string;
    notes: string | null;
    weekly_payment: number | null;
    discount: number;
    has_payments: boolean;
    lines: CartLineInput[];
}

const today = () => {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

const sameLine = (a: Omit<CartLineInput, 'packets'>, b: Omit<CartLineInput, 'packets'>) =>
    a.item_id === b.item_id &&
    (a.lens_power_id ?? null) === (b.lens_power_id ?? null) &&
    JSON.stringify([...a.colors].sort((x, y) => x.color_id - y.color_id)) === JSON.stringify([...b.colors].sort((x, y) => x.color_id - y.color_id));

/** The new sale page and the edit of an order: the same checkout cart. */
export function OrderFormPage() {
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', 'orders', id, 'edit'], queryFn: () => api.get<{ data: EditData }>(`/orders/${id}/edit`), enabled: Boolean(id) });
    if (id && isLoading) return <PageSpinner />;
    if (id && (error || !data)) return <QueryError error={error} />;
    return <CheckoutForm key={id ?? 'new'} order={data?.data ?? null} />;
}

/**
 * The checkout cart (the panel's CreateOrder and EditOrder, built like the app's checkout): the seller searches the items, chooses a
 * power and the colors of the packets on an item's card, and adds whole packets; the cart beside them shows each line with its pieces,
 * its dozens at the price of a dozen, and the total, with one discount off the whole sale (an amount or a percent). The prices and
 * every sale rule are the server's. On an edit, an order with payments keeps its customer and its payment type, and its down payment
 * is not edited.
 */
function CheckoutForm({ order }: { order: EditData | null }) {
    const t = useT();
    const navigate = useNavigate();
    const key = 'filament/resources/order.';
    const field = (name: string) => t(`${key}fields.${name}`);
    const checkout = (name: string, replace?: Record<string, string | number>) => t(`${key}checkout.${name}`, replace);

    const form = useForm({
        customer_id: (order?.customer_id ?? null) as number | null,
        payment_type: order?.payment_type ?? '',
        date: order?.date ?? today(),
        notes: order?.notes ?? '',
        down_payment: '',
        weekly_payment: order?.weekly_payment ? String(order.weekly_payment) : '',
    });
    const [cart, setCart] = useState<CartLineInput[]>(order?.lines ?? []);
    const [discount, setDiscount] = useState<number>(order?.discount ?? 0);
    const [discountPercent, setDiscountPercent] = useState<number | null>(null);
    const [searchText, setSearchText] = useState('');
    const [search, setSearch] = useState('');
    const [chosenPowers, setChosenPowers] = useState<Record<string, number | 'none'>>({});
    const [chosenTypes, setChosenTypes] = useState<Record<string, string>>({});
    const [typed, setTyped] = useState<Record<number, string>>({});

    useEffect(() => {
        const timer = setTimeout(() => setSearch(searchText.trim()), 300);
        return () => clearTimeout(timer);
    }, [searchText]);

    const body = { order_id: order?.id, search, cart, chosen_powers: chosenPowers, chosen_types: chosenTypes, discount, discount_percent: discountPercent };
    const { data, isLoading, isFetching } = useQuery({
        queryKey: ['sales-checkout', body],
        queryFn: () => api.post<{ items: ItemCard[]; summary: CartSummary }>('/orders/checkout', body),
        placeholderData: keepPreviousData,
    });
    const items = data?.items ?? [];
    const summary = data?.summary;
    const isPostpaid = form.values.payment_type === 'postpaid';
    const locked = Boolean(order?.has_payments);

    const notEnough = (packets: number, dozens: string) => toast.error(checkout('not_enough', { packets, dozens }));

    const addPackets = (card: ItemCard) => {
        if (!card.line) return notEnough(0, '0');
        const wanted = Math.max(1, Math.floor(Number(typed[card.id] || 1)));
        let packets = card.inCart + wanted;
        if (packets > card.packetsLeft) {
            notEnough(card.packetsLeft, card.dozensLeft);
            packets = card.packetsLeft;
        }
        if (packets <= 0) return;
        const line = card.line;
        setCart((current) => {
            const exists = current.some((entry) => sameLine(entry, line));
            return exists ? current.map((entry) => (sameLine(entry, line) ? { ...entry, packets } : entry)) : [...current, { ...line, packets }];
        });
        setTyped((current) => ({ ...current, [card.id]: '' }));
    };

    const setPackets = (index: number, packets: number) => {
        const line = summary?.lines[index];
        if (!line) return;
        const match = (entry: CartLineInput) => sameLine(entry, { item_id: line.item_id, lens_power_id: line.lens_power_id, colors: line.colorIds });
        if (packets <= 0) {
            setCart((current) => current.filter((entry) => !match(entry)));
            return;
        }
        if (packets > line.packetsLeft) {
            notEnough(line.packetsLeft, line.dozensLeft);
            packets = line.packetsLeft;
        }
        setCart((current) => current.map((entry) => (match(entry) ? { ...entry, packets } : entry)));
    };

    const setDiscountMode = (mode: 'amount' | 'percent') => {
        if (!summary) return;
        if (mode === 'percent' && discountPercent === null) setDiscountPercent(summary.subtotal > 0 ? Math.round((summary.discount / summary.subtotal) * 10000) / 100 : 0);
        if (mode === 'amount') {
            setDiscount(summary.discount);
            setDiscountPercent(null);
        }
    };

    const save = useSave(
        () => {
            const payload = {
                ...form.values,
                down_payment: !order && isPostpaid && form.values.down_payment !== '' ? Number(form.values.down_payment) : null,
                weekly_payment: isPostpaid && form.values.weekly_payment !== '' ? Number(form.values.weekly_payment) : null,
                discount,
                discount_percent: discountPercent,
                lines: cart,
            };
            return order ? api.put<{ data: { id: number } }>(`/orders/${order.id}`, payload) : api.post<{ data: { id: number } }>('/orders', payload);
        },
        {
            invalidate: ['list', 'record', 'sales', 'metric'],
            success: order ? t('admin.actions.saved') : t('admin/sales.orders.created'),
            onSuccess: (result) => navigate(`/orders/${result.data.id}`),
            onError: form.fail,
        },
    );

    const submit = () => {
        if (cart.length === 0) return toast.error(checkout('cart_empty'));
        save.mutate(undefined);
    };

    const cartError = form.errors.items ?? form.errors.lines ?? form.errors.discount ?? form.errors.order;

    return (
        <>
            <PageHeader
                breadcrumbs={[{ label: t(`${key}plural_label`), to: '/orders' }, ...(order ? [{ label: `#${order.id}`, to: `/orders/${order.id}` }] : [])]}
                title={order ? `${t('admin.actions.edit')} #${order.id}` : `${t('admin.actions.new')} · ${t(`${key}label`)}`}
                actions={<Button onClick={() => navigate(order ? `/orders/${order.id}` : '/orders')}>{t('admin.actions.cancel')}</Button>}
            />

            <Card className="mb-5">
                <CardBody className="grid gap-4 md:grid-cols-3">
                    <Field label={field('customer_id')} required error={form.errors.customer_id} hint={locked ? t('orders.validation.paid_order_keeps_customer') : undefined}>
                        {(fieldId) => (
                            <SearchSelect
                                id={fieldId}
                                endpoint="/options/customers"
                                value={form.values.customer_id}
                                initialLabel={order?.customer_name}
                                disabled={locked}
                                invalid={Boolean(form.errors.customer_id)}
                                onChange={(value) => form.set('customer_id', value === null ? null : Number(value))}
                            />
                        )}
                    </Field>
                    <Field label={field('payment_type')} required error={form.errors.payment_type} hint={locked ? t('orders.validation.paid_order_keeps_payment_type') : undefined}>
                        {(fieldId) => (
                            <Select
                                id={fieldId}
                                value={form.values.payment_type}
                                disabled={locked}
                                invalid={Boolean(form.errors.payment_type)}
                                placeholder={t('admin.form.choose')}
                                options={[
                                    { value: 'cash', label: t('orders.payment_types.cash') },
                                    { value: 'postpaid', label: t('orders.payment_types.postpaid') },
                                ]}
                                onChange={(event) => form.set('payment_type', event.target.value)}
                            />
                        )}
                    </Field>
                    <Field label={field('date')} required error={form.errors.date}>
                        {(fieldId) => <Input id={fieldId} type="date" value={form.values.date} invalid={Boolean(form.errors.date)} onChange={(event) => form.set('date', event.target.value)} />}
                    </Field>
                </CardBody>
            </Card>

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
                <Card>
                    <CardHeader
                        icon={<Package className="size-4.5" />}
                        title={field('items')}
                        actions={
                            <div className="relative w-64 max-w-full">
                                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                                <Input value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder={checkout('search')} className="h-9 ps-9" />
                            </div>
                        }
                    />
                    <CardBody className="relative">
                        {isFetching && !isLoading && <div className="absolute inset-x-0 top-0 h-0.5 animate-pulse bg-accent/60" />}
                        {isLoading && (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {Array.from({ length: 6 }).map((_, index) => (
                                    <Skeleton key={index} className="h-44" />
                                ))}
                            </div>
                        )}
                        {!isLoading && items.length === 0 && <Empty title={checkout('no_results')} />}
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {items.map((card) => (
                                <div key={card.id} className={cn('flex flex-col gap-3 rounded-xl border border-line p-3.5', card.inCart > 0 && 'border-accent/50 bg-accent-soft/20', card.isOutOfStock && 'opacity-60')}>
                                    <div className="flex items-start gap-3">
                                        <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-subtle text-faint">
                                            {card.image ? <img src={card.image} alt="" className="size-full object-cover" /> : <ImageIcon className="size-5" />}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">{card.name}</p>
                                            <p className="text-xs text-muted">
                                                <span className="font-semibold text-ink tabular-nums">{card.price}</span> / {checkout('per_dozen')}
                                            </p>
                                            <p className="text-xs text-muted">{checkout('packet_size', { pieces: card.piecesPerPacket })}</p>
                                        </div>
                                    </div>

                                    {card.powers.length > 0 && (
                                        <div>
                                            <p className="mb-1 text-[11px] font-medium text-muted">{checkout('power')}</p>
                                            <div className="flex flex-wrap gap-1">
                                                {card.powers.map((power) => (
                                                    <button
                                                        key={power.id ?? 'none'}
                                                        type="button"
                                                        onClick={() => setChosenPowers((current) => ({ ...current, [card.id]: power.id ?? 'none' }))}
                                                        className={cn('rounded-md border px-2 py-1 text-xs tabular-nums', power.isSelected ? 'border-accent bg-accent text-white' : 'border-line hover:bg-subtle')}
                                                        title={checkout('dozens', { value: power.dozens })}
                                                    >
                                                        {power.value}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {card.showsTypes && (
                                        <div>
                                            <p className="mb-1 text-[11px] font-medium text-muted">{checkout('packet_colors')}</p>
                                            <div className="flex flex-col gap-1">
                                                {card.types.map((type) => (
                                                    <button
                                                        key={type.key}
                                                        type="button"
                                                        onClick={() => setChosenTypes((current) => ({ ...current, [`${card.id}|${card.powerKey}`]: type.key }))}
                                                        className={cn('flex items-center justify-between gap-2 rounded-md border px-2 py-1 text-start text-xs', type.isSelected ? 'border-accent bg-accent-soft/60' : 'border-line hover:bg-subtle')}
                                                    >
                                                        <span className="truncate">{type.colors.length ? type.colors.map((color) => `${color.name} ${color.pieces}`).join(' · ') : checkout('no_colors')}</span>
                                                        <span className="shrink-0 text-muted tabular-nums">{checkout('packets', { value: type.packets })}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="mt-auto space-y-2">
                                        {card.isOutOfStock ? (
                                            <Badge tone="danger">{checkout('out_of_stock')}</Badge>
                                        ) : (
                                            <p className="text-xs text-muted">{checkout('available', { dozens: card.dozensLeft, packets: card.packetsLeft })}</p>
                                        )}
                                        {card.inCart > 0 && (
                                            <p className="text-xs font-medium text-accent">
                                                {t('admin/sales.checkout.in_cart', { packets: card.inCart })} · {card.inCartBreakdown}
                                            </p>
                                        )}
                                        <div className="flex gap-2">
                                            <Input
                                                type="number"
                                                min={1}
                                                dir="ltr"
                                                placeholder="1"
                                                disabled={card.isOutOfStock}
                                                value={typed[card.id] ?? ''}
                                                onChange={(event) => setTyped((current) => ({ ...current, [card.id]: event.target.value }))}
                                                onKeyDown={(event) => event.key === 'Enter' && addPackets(card)}
                                                className="h-8 w-20 text-center"
                                                aria-label={checkout('packets_label')}
                                            />
                                            <Button size="sm" variant="primary" className="flex-1" icon={<Plus className="size-3.5" />} disabled={card.isOutOfStock} onClick={() => addPackets(card)}>
                                                {checkout('add')}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardBody>
                </Card>

                <Card className="lg:sticky lg:top-20">
                    <CardHeader
                        icon={<ShoppingCart className="size-4.5" />}
                        title={checkout('cart')}
                        actions={
                            cart.length > 0 && (
                                <Button size="sm" variant="ghost" icon={<X className="size-3.5" />} onClick={() => { setCart([]); setDiscount(0); setDiscountPercent(null); }}>
                                    {checkout('clear')}
                                </Button>
                            )
                        }
                    />
                    {cart.length === 0 ? (
                        <Empty icon={<ShoppingCart className="size-5" />} title={checkout('cart_empty')} description={checkout('cart_empty_hint')} />
                    ) : (
                        <ul className="divide-y divide-line">
                            {(summary?.lines ?? []).map((line, index) => (
                                <li key={line.key} className="px-5 py-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{line.name}</p>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {line.power !== null && <Badge tone="info">{checkout('power_value', { value: String(line.power) })}</Badge>}
                                                {line.colors.map((color) => (
                                                    <Badge key={color.name}>{`${color.name} ${color.pieces}`}</Badge>
                                                ))}
                                            </div>
                                        </div>
                                        <button type="button" onClick={() => setPackets(index, 0)} className="rounded-md p-1 text-faint hover:bg-danger-soft hover:text-danger" aria-label={checkout('remove')}>
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between gap-3">
                                        <div className="flex items-center rounded-lg border border-line">
                                            <button type="button" className="p-1.5 text-muted hover:text-ink" onClick={() => setPackets(index, line.packets - 1)} aria-label={checkout('remove')}>
                                                <Minus className="size-3.5" />
                                            </button>
                                            <input
                                                type="number"
                                                dir="ltr"
                                                value={line.packets}
                                                onChange={(event) => setPackets(index, Math.floor(Number(event.target.value) || 0))}
                                                className="w-12 bg-transparent text-center text-sm tabular-nums outline-none"
                                                aria-label={checkout('packets_label')}
                                            />
                                            <button type="button" className="p-1.5 text-muted hover:text-ink" onClick={() => setPackets(index, line.packets + 1)} aria-label={checkout('add')}>
                                                <Plus className="size-3.5" />
                                            </button>
                                        </div>
                                        <span className="text-sm font-semibold tabular-nums">{line.totalText}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-muted tabular-nums">{line.breakdown}</p>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="space-y-4 border-t border-line px-5 py-4">
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted">{checkout('subtotal')}</dt>
                                <dd className="tabular-nums">{summary?.subtotalText ?? '0'}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-muted">{checkout('discount')}</dt>
                                <dd className="flex items-center gap-1.5">
                                    <Input
                                        type="number"
                                        min={0}
                                        dir="ltr"
                                        value={discountPercent ?? (discount || '')}
                                        placeholder="0"
                                        onChange={(event) => {
                                            const value = Math.max(0, Number(event.target.value) || 0);
                                            if (discountPercent !== null) setDiscountPercent(Math.min(100, value));
                                            else setDiscount(value);
                                        }}
                                        className="h-8 w-28 text-end"
                                    />
                                    <div className="inline-flex rounded-lg bg-subtle p-0.5 text-xs">
                                        {(['amount', 'percent'] as const).map((mode) => (
                                            <button
                                                key={mode}
                                                type="button"
                                                onClick={() => setDiscountMode(mode)}
                                                className={cn('rounded-md px-2 py-1', (mode === 'percent') === (discountPercent !== null) ? 'bg-surface shadow-sm' : 'text-muted')}
                                            >
                                                {mode === 'percent' ? '%' : t('admin/sales.checkout.dinar')}
                                            </button>
                                        ))}
                                    </div>
                                </dd>
                            </div>
                            {discountPercent !== null && summary && <p className="text-end text-xs text-muted">{checkout('discount_amount', { amount: summary.discountText })}</p>}
                            <div className="flex justify-between border-t border-line pt-2 text-base font-semibold">
                                <dt>{checkout('net_total')}</dt>
                                <dd className="tabular-nums">{summary?.totalText ?? '0'}</dd>
                            </div>
                            <p className="text-xs text-faint">{checkout('price_by_system')}</p>
                        </dl>

                        {isPostpaid && !order && (
                            <Field label={field('down_payment')} error={form.errors.down_payment}>
                                {(fieldId) => <MoneyInput id={fieldId} placeholder={checkout('optional')} value={form.values.down_payment} invalid={Boolean(form.errors.down_payment)} onChange={(value) => form.set('down_payment', value)} />}
                            </Field>
                        )}
                        {isPostpaid && (
                            <Field label={field('weekly_payment')} error={form.errors.weekly_payment} hint={checkout('weekly_payment_hint')}>
                                {(fieldId) => <MoneyInput id={fieldId} placeholder={checkout('optional')} value={form.values.weekly_payment} invalid={Boolean(form.errors.weekly_payment)} onChange={(value) => form.set('weekly_payment', value)} />}
                            </Field>
                        )}
                        <Field label={field('notes')} error={form.errors.notes}>
                            {(fieldId) => <Textarea id={fieldId} rows={2} placeholder={checkout('optional')} value={form.values.notes} onChange={(event) => form.set('notes', event.target.value)} />}
                        </Field>

                        {cartError && <p className="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{cartError}</p>}

                        <Button variant="primary" className="w-full" loading={save.isPending} onClick={submit}>
                            {order ? t('admin.actions.save_changes') : checkout('confirm')}
                        </Button>
                    </div>
                </Card>
            </div>
        </>
    );
}

