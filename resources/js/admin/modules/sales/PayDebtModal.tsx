import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { CheckCircle2, Printer } from 'lucide-react';
import { api, ApiError } from '../../lib/api';
import { cn, date as formatDate, money, number } from '../../lib/format';
import { useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Button, Field, Input, Modal, Select, Skeleton, Textarea } from '../../components/ui';

interface FormOrder {
    id: number;
    date: string;
    total: number;
    remaining: number;
    weekly_payment: number | null;
    late: number;
    next_amount: number;
    next_due_date: string | null;
    is_due_today: boolean;
}

interface PayForm {
    customer: { id: number; name: string } | null;
    customers?: { value: number; label: string; description: string | null }[];
    order?: { id: number } | null;
    debt: number;
    orders: FormOrder[];
}

const today = () => {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

/** An amount as typed, kept with thousands separators: "30000" shows as "30,000". */
function typedAmount(value: string): string {
    const clean = value.replace(/[^\d.]/g, '');
    const [whole, ...decimals] = clean.split('.');
    const grouped = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return decimals.length ? `${grouped}.${decimals.join('').slice(0, 2)}` : grouped;
}

const amountOf = (value: string | undefined) => Math.max(Number((value ?? '').replace(/,/g, '')) || 0, 0);

/**
 * The payment window (the panel's pay debt action): one amount for each order the customer still owes on, each with a button that
 * fills what is left on it, a preview of the debt, what is being paid, and what remains, then the date, a receipt image, and notes.
 * Opened on a customer it pays that customer's orders; opened on an order, only that order; opened with neither, it asks for the customer.
 * Once recorded, the receipt can be printed from the window.
 */
export function PayDebtModal({ customerId, orderId, onClose }: { customerId?: number | null; orderId?: number | null; onClose: () => void }) {
    const t = useT();
    const key = 'filament/resources/customer.actions.pay_debt.';
    const [chosenCustomer, setChosenCustomer] = useState<number | null>(customerId ?? null);
    const [amounts, setAmounts] = useState<Record<number, string>>({});
    const [paymentDate, setPaymentDate] = useState(today());
    const [notes, setNotes] = useState('');
    const [image, setImage] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [receipt, setReceipt] = useState<string | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['sales-pay-form', chosenCustomer, orderId],
        queryFn: () => api.get<PayForm>('/customer-payments/form', { customer_id: chosenCustomer, order_id: orderId }),
    });

    const debt = data?.debt ?? 0;
    const paying = useMemo(() => Math.min(Object.values(amounts).reduce((sum, value) => sum + amountOf(value), 0), debt), [amounts, debt]);
    const after = debt - paying;

    const save = useSave(
        () => {
            const body = new FormData();
            body.append('customer_id', String(data?.customer?.id ?? chosenCustomer ?? ''));
            Object.entries(amounts).forEach(([order, value]) => amountOf(value) > 0 && body.append(`amounts[${order}]`, String(amountOf(value))));
            body.append('date', paymentDate);
            if (notes) body.append('notes', notes);
            if (image) body.append('image', image);
            return api.post<{ data: { receipt_url: string | null }; message: string }>('/customer-payments', body);
        },
        {
            invalidate: ['list', 'record', 'sales', 'metric'],
            onSuccess: (result) => setReceipt(result.data.receipt_url ?? ''),
            onError: (error) => {
                if (error instanceof ApiError && error.status === 422) {
                    setErrors(Object.fromEntries(Object.entries(error.errors).map(([field, messages]) => [field, messages[0]])));
                }
            },
        },
    );

    const title = orderId && data?.customer
        ? t(`${key}heading_order`, { order: orderId, customer: data.customer.name })
        : data?.customer
          ? t(`${key}heading`, { customer: data.customer.name })
          : t(`${key}label`);

    if (receipt !== null) {
        return (
            <Modal open onClose={onClose} size="sm" title={t(`${key}label`)} footer={<Button onClick={onClose}>{t('admin.actions.close')}</Button>}>
                <div className="flex flex-col items-center gap-3 py-4 text-center">
                    <CheckCircle2 className="size-10 text-success" />
                    <p className="font-medium">{t(`${key}done`)}</p>
                    {receipt && (
                        <a href={receipt} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-lg bg-accent px-3.5 py-2 text-sm font-medium text-white hover:bg-accent-strong">
                            <Printer className="size-4" />
                            {t('filament/resources/customer_payment.actions.print_receipt')}
                        </a>
                    )}
                </div>
            </Modal>
        );
    }

    const errorText = errors.amount ?? errors.order ?? errors.amounts ?? errors.customer_id;

    return (
        <Modal
            open
            onClose={onClose}
            size="lg"
            title={title}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="success" loading={save.isPending} disabled={!data?.customer || paying <= 0} onClick={() => { setErrors({}); save.mutate(undefined); }}>
                        {t(`${key}submit`)}
                    </Button>
                </>
            }
        >
            <div className="space-y-5">
                {!customerId && !orderId && (
                    <Field label={t(`${key}customer`)} required error={errors.customer_id}>
                        {(id) => (
                            <Select
                                id={id}
                                value={chosenCustomer ?? ''}
                                placeholder={t('admin.form.choose')}
                                options={(data?.customers ?? []).map((customer) => ({ value: customer.value, label: customer.description ? `${customer.label} · ${customer.description}` : customer.label }))}
                                onChange={(event) => {
                                    setChosenCustomer(event.target.value ? Number(event.target.value) : null);
                                    setAmounts({});
                                }}
                            />
                        )}
                    </Field>
                )}

                {isLoading && <Skeleton className="h-24" />}

                {data?.customer && (
                    <div className="space-y-3">
                        {data.orders.map((order) => (
                            <div key={order.id} className="rounded-xl border border-line p-3.5">
                                <div className="flex flex-wrap items-end gap-3">
                                    <Field className="min-w-48 flex-1" label={t(`${key}preview.order`, { id: order.id, date: formatDate(order.date) })}>
                                        {(id) => (
                                            <Input
                                                id={id}
                                                inputMode="decimal"
                                                dir="ltr"
                                                placeholder="0"
                                                value={amounts[order.id] ?? ''}
                                                onChange={(event) => setAmounts((current) => ({ ...current, [order.id]: typedAmount(event.target.value) }))}
                                                invalid={amountOf(amounts[order.id]) > order.remaining + 0.0001}
                                            />
                                        )}
                                    </Field>
                                    <Button onClick={() => setAmounts((current) => ({ ...current, [order.id]: typedAmount(String(Math.round(order.remaining * 100) / 100)) }))}>
                                        {t(`${key}pay_order_all`)}
                                    </Button>
                                </div>
                                <p className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted">
                                    <span>{t(`${key}preview.order_remaining`, { amount: number(order.remaining) })}</span>
                                    {order.late > 0.0001 && <span className="text-danger">{t(`${key}order_late`, { amount: number(order.late) })}</span>}
                                    {order.weekly_payment !== null && <span>{t(`${key}order_weekly`, { amount: number(order.weekly_payment) })}</span>}
                                    {order.next_due_date &&
                                        (order.is_due_today ? (
                                            <span className="text-[oklch(0.5_0.12_60)]">{t(`${key}order_next_today`, { amount: number(order.next_amount) })}</span>
                                        ) : (
                                            <span>{t(`${key}order_next`, { amount: number(order.next_amount), date: formatDate(order.next_due_date) })}</span>
                                        ))}
                                </p>
                            </div>
                        ))}

                        <div className="grid grid-cols-3 gap-2 rounded-xl bg-subtle p-3.5 text-center">
                            {[
                                { label: t(`${key}preview.current_debt`), value: debt, tone: '' },
                                { label: t(`${key}preview.paying`), value: paying, tone: 'text-success' },
                                { label: t(`${key}preview.after`), value: after, tone: after <= 0.0001 ? 'text-success' : 'text-danger' },
                            ].map((cell) => (
                                <div key={cell.label}>
                                    <p className="text-xs text-muted">{cell.label}</p>
                                    <p className={cn('mt-1 text-sm font-semibold tabular-nums', cell.tone)}>{money(cell.value)}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {!isLoading && !data?.customer && <p className="text-sm text-muted">{t(`${key}preview.choose_customer`)}</p>}

                {errorText && <p className="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{errorText}</p>}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t(`${key}date`)} required error={errors.date}>
                        {(id) => <Input id={id} type="date" value={paymentDate} onChange={(event) => setPaymentDate(event.target.value)} />}
                    </Field>
                    <Field label={t(`${key}image`)} error={errors.image}>
                        {(id) => <Input id={id} type="file" accept="image/jpeg,image/png,image/webp" className="py-1.5" onChange={(event) => setImage(event.target.files?.[0] ?? null)} />}
                    </Field>
                </div>
                <Field label={t(`${key}notes`)} error={errors.notes}>
                    {(id) => <Textarea id={id} rows={2} value={notes} onChange={(event) => setNotes(event.target.value)} />}
                </Field>
            </div>
        </Modal>
    );
}
