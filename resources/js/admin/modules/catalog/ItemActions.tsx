import { useState } from 'react';
import { api } from '../../lib/api';
import { number } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Button, ConfirmDialog, Field, Input, Modal, MoneyInput } from '../../components/ui';
import { SearchSelect } from '../../components/SearchSelect';

/** What every list and page that touches an item reads again after an item action. */
export const ITEM_INVALIDATE = ['list', 'record', 'metric', 'options', 'dashboard', 'item'];

/** A money field's text without its thousands separators. */
function money(value: string | number | null | undefined): number {
    return Number(String(value ?? '').replace(/,/g, '')) || 0;
}

/** A number as the money fields write it: thousands separators, and decimals only when it has them. */
function formatted(value: number): string {
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Math.round(value * 100) / 100);
}

/** What the margin is worth out of the sale price, in percent. A price of nothing earns nothing. */
function percentage(sale: number, cost: number): string {
    return sale <= 0 ? '0' : String(Math.round(((sale - cost) / sale) * 10000) / 100);
}

/** The sale price that earns the given percentage of itself. A percentage of a hundred or more keeps the cost. */
function salePriceOfPercentage(cost: number, percent: number): number {
    return percent >= 100 ? cost : Math.round((cost / (1 - percent / 100)) * 100) / 100;
}

export interface PricedItem {
    id: number;
    name: string;
    sale_price: number;
    cost_price: number;
}

/**
 * Fix the price of one item: the sale price itself, the margin wanted on one dozen, or the margin as a percentage of the sale price.
 * Writing any one of the three works the other two out from the cost price, and only the sale price is saved.
 */
export function SetSalePriceModal({ item, onClose }: { item: PricedItem; onClose: () => void }) {
    const t = useT();
    const cost = Number(item.cost_price) || 0;
    const form = useForm({
        sale_price: formatted(Number(item.sale_price) || 0),
        profit_margin: formatted((Number(item.sale_price) || 0) - cost),
        profit_margin_percentage: percentage(Number(item.sale_price) || 0, cost),
    });
    const save = useSave((salePrice: number) => api.put(`/items/${item.id}/sale-price`, { sale_price: salePrice }), {
        invalidate: ITEM_INVALIDATE,
        success: t('filament/resources/item.actions.set_sale_price.saved'),
        onSuccess: onClose,
        onError: form.fail,
    });

    const fromSale = (text: string) => {
        const sale = money(text);
        form.setValues({ sale_price: text, profit_margin: formatted(sale - cost), profit_margin_percentage: percentage(sale, cost) });
    };
    const fromMargin = (text: string) => {
        const sale = cost + money(text);
        form.setValues({ sale_price: formatted(sale), profit_margin: text, profit_margin_percentage: percentage(sale, cost) });
    };
    const fromPercentage = (text: string) => {
        const sale = salePriceOfPercentage(cost, Number(text) || 0);
        form.setValues({ sale_price: formatted(sale), profit_margin: formatted(sale - cost), profit_margin_percentage: text });
    };
    const submit = () => save.mutate(money(form.values.sale_price));
    const percentTooHigh = Number(form.values.profit_margin_percentage) > 99;

    return (
        <Modal
            open
            onClose={onClose}
            size="md"
            title={t('filament/resources/item.actions.set_sale_price.heading', { item: item.name })}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={save.isPending} disabled={percentTooHigh} onClick={submit}>
                        {t('admin.actions.save')}
                    </Button>
                </>
            }
        >
            <form className="grid gap-4" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <Field label={t('filament/resources/item.fields.cost_price')} hint={t('filament/resources/item.actions.set_sale_price.cost_hint')}>
                    {(id) => <Input id={id} value={number(cost)} disabled dir="ltr" />}
                </Field>
                <Field label={t('filament/resources/item.fields.sale_price')} hint={t('filament/resources/item.actions.set_sale_price.sale_price_hint')} error={form.errors.sale_price} required>
                    {(id) => <MoneyInput id={id} autoFocus value={form.values.sale_price} onChange={fromSale} invalid={Boolean(form.errors.sale_price)} />}
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('filament/resources/item.fields.profit_margin')} hint={t('filament/resources/item.actions.set_sale_price.profit_margin_hint')}>
                        {(id) => <MoneyInput id={id} value={form.values.profit_margin} onChange={fromMargin} />}
                    </Field>
                    <Field label={t('filament/resources/item.fields.profit_margin_percentage')} hint={t('filament/resources/item.actions.set_sale_price.profit_margin_percentage_hint')}>
                        {(id) => (
                            <div className="relative">
                                <Input id={id} type="number" max={99} step="0.01" dir="ltr" className="pe-8" value={form.values.profit_margin_percentage} onChange={(event) => fromPercentage(event.target.value)} invalid={percentTooHigh} />
                                <span className="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-sm text-muted">%</span>
                            </div>
                        )}
                    </Field>
                </div>
            </form>
        </Modal>
    );
}

/** Choose the supplier of one item, or of several selected items at once. */
export function SetSupplierModal({ ids, onClose, onDone }: { ids: number[]; onClose: () => void; onDone?: () => void }) {
    const t = useT();
    const [supplierId, setSupplierId] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>();
    const save = useSave(
        (supplier: number | null) => (ids.length === 1 ? api.put(`/items/${ids[0]}/supplier`, { supplier_id: supplier }) : api.post('/items/supplier', { ids, supplier_id: supplier })),
        {
            invalidate: ITEM_INVALIDATE,
            success: t('filament/resources/item.actions.set_supplier.saved'),
            onSuccess: () => {
                onDone?.();
                onClose();
            },
            onError: (failure) => setError(failure instanceof Error ? failure.message : String(failure)),
        },
    );

    return (
        <Modal
            open
            onClose={onClose}
            size="sm"
            title={t('filament/resources/item.actions.set_supplier.label')}
            description={ids.length > 1 ? t('admin.table.selected', { count: ids.length }) : undefined}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={save.isPending} disabled={!supplierId} onClick={() => save.mutate(supplierId)}>
                        {t('admin.actions.save')}
                    </Button>
                </>
            }
        >
            <Field label={t('filament/resources/item.fields.supplier_id')} error={error} required>
                {(id) => <SearchSelect id={id} endpoint="/options/suppliers" value={supplierId} onChange={(value) => { setSupplierId(value === null ? null : Number(value)); setError(undefined); }} invalid={Boolean(error)} />}
            </Field>
        </Modal>
    );
}

/** Activate an inactive item, so its quantity can be sold again. */
export function ActivateDialog({ item, onClose }: { item: { id: number; name: string }; onClose: () => void }) {
    const t = useT();
    const save = useSave(() => api.post(`/items/${item.id}/activate`), { invalidate: ITEM_INVALIDATE, success: t('filament/resources/item.actions.activate.saved'), onSuccess: onClose });
    return (
        <ConfirmDialog
            open
            tone="primary"
            onClose={onClose}
            onConfirm={() => save.mutate(undefined)}
            loading={save.isPending}
            title={t('filament/resources/item.actions.activate.heading', { item: item.name })}
            description={t('filament/resources/item.actions.activate.description')}
            confirmLabel={t('filament/resources/item.actions.activate.label')}
        />
    );
}

/** Work the item's quantities and cost out again from the purchases and the orders themselves. */
export function RecalculateStockDialog({ item, onClose }: { item: { id: number; name: string }; onClose: () => void }) {
    const t = useT();
    const save = useSave(() => api.post(`/items/${item.id}/recalculate-stock`), { invalidate: ITEM_INVALIDATE, success: t('filament/resources/item.actions.recalculate_stock.saved'), onSuccess: onClose });
    return (
        <ConfirmDialog
            open
            tone="primary"
            onClose={onClose}
            onConfirm={() => save.mutate(undefined)}
            loading={save.isPending}
            title={t('filament/resources/item.actions.recalculate_stock.heading', { item: item.name })}
            description={t('filament/resources/item.actions.recalculate_stock.description')}
            confirmLabel={t('filament/resources/item.actions.recalculate_stock.label')}
        />
    );
}
