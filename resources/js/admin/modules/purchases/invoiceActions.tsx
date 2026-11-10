import { useState, type ReactNode } from 'react';
import { ArrowRightCircle, BadgeCheck, Eye, Pencil, Printer, QrCode, Undo2 } from 'lucide-react';
import { useNavigate } from 'react-router';
import { api, ApiError } from '../../lib/api';
import { useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { ConfirmDialog, Field, MoneyInput, toast, type MenuItem } from '../../components/ui';
import { useRecordActions } from '../../components/RecordActions';
import { yuan, type PurchaseInvoiceRow } from './shared';

type Pending = { kind: 'advance' | 'confirm' | 'revert'; invoice: PurchaseInvoiceRow } | null;

/**
 * The next steps of a purchase invoice, as the panel offers them: move it to its next stage, confirm it for the side it waits for
 * (writing its amount again), take back its last confirmation (a super user only), print it and its carton label, open it, edit it,
 * and delete or restore it. Each shows only when the server says the user may take it.
 */
export function useInvoiceActions(options: { onDeleted?: () => void } = {}) {
    const t = useT();
    const navigate = useNavigate();
    const [pending, setPending] = useState<Pending>(null);
    const [amount, setAmount] = useState('');
    const [amountError, setAmountError] = useState<string | undefined>();
    const records = useRecordActions<PurchaseInvoiceRow>('/purchase-invoices', { label: (invoice) => invoice.name, onDeleted: options.onDeleted });
    const invalidate = ['list', 'record', 'metric', '/purchase-invoices'];
    const close = () => {
        setPending(null);
        setAmount('');
        setAmountError(undefined);
    };
    const done = (result: { message: string }) => {
        toast.success(result.message);
        close();
    };

    const advance = useSave((invoice: PurchaseInvoiceRow) => api.post<{ message: string }>(`/purchase-invoices/${invoice.id}/advance-stage`), { invalidate, onSuccess: done });
    const revert = useSave((invoice: PurchaseInvoiceRow) => api.post<{ message: string }>(`/purchase-invoices/${invoice.id}/revert-confirmation`), { invalidate, onSuccess: done });
    const confirm = useSave((body: { invoice: PurchaseInvoiceRow; amount: string }) => api.post<{ message: string }>(`/purchase-invoices/${body.invoice.id}/confirm`, { amount: body.amount }), {
        invalidate,
        onSuccess: done,
        onError: (error) => {
            const message = error instanceof ApiError ? error.first('amount') : undefined;
            if (message) setAmountError(message);
            else toast.error(error instanceof ApiError ? (error.first() ?? error.message) : String(error));
        },
    });

    /** The two next steps, shown as buttons on the invoice's page. */
    const steps = (invoice: PurchaseInvoiceRow): { advance: MenuItem; confirm: MenuItem } => ({
        advance: {
            label: t('filament/resources/purchase_invoice.actions.advance_stage.label', { stage: invoice.can.next_stage?.label ?? '' }),
            icon: <ArrowRightCircle className="size-4" />,
            onSelect: () => setPending({ kind: 'advance', invoice }),
            hidden: !invoice.can.advance_stage,
        },
        confirm: {
            label: t('filament/resources/purchase_invoice.actions.confirm.label', { side: invoice.awaited_confirmation?.label ?? '' }),
            icon: <BadgeCheck className="size-4" />,
            onSelect: () => setPending({ kind: 'confirm', invoice }),
            hidden: !invoice.can.confirm,
        },
    });

    /**
     * Printing, taking back a confirmation, and deleting or restoring: the "More" menu of the invoice's page.
     * On the page, an invoice with payoffs (its initial payment too) hides its delete, and one that ever had payoffs its delete forever.
     */
    const more = (invoice: PurchaseInvoiceRow & { has_payoffs?: boolean; has_any_payoffs?: boolean }): MenuItem[] => [
        { label: t('filament/resources/purchase_invoice.actions.print'), icon: <Printer className="size-4" />, href: invoice.print_url, hidden: Boolean(invoice.deleted_at) },
        { label: t('filament/resources/purchase_invoice.actions.print_label'), icon: <QrCode className="size-4" />, href: invoice.label_url, hidden: Boolean(invoice.deleted_at) },
        {
            label: t('filament/resources/purchase_invoice.actions.revert_confirmation.label'),
            icon: <Undo2 className="size-4" />,
            tone: 'danger',
            onSelect: () => setPending({ kind: 'revert', invoice }),
            hidden: !invoice.can.revert_confirmation,
        },
        ...records.items({
            ...invoice,
            can: { ...invoice.can, delete: invoice.can.delete && !invoice.has_payoffs, force_delete: invoice.can.force_delete && !invoice.has_any_payoffs },
        }),
    ];

    /** Every action of a row of the list. */
    const items = (invoice: PurchaseInvoiceRow): MenuItem[] => {
        const { advance, confirm } = steps(invoice);
        return [
            advance,
            confirm,
            { label: t('admin.actions.view'), icon: <Eye className="size-4" />, onSelect: () => navigate(`/purchase-invoices/${invoice.id}`), hidden: !invoice.can.view },
            { label: t('admin.actions.edit'), icon: <Pencil className="size-4" />, onSelect: () => navigate(`/purchase-invoices/${invoice.id}/edit`), hidden: !invoice.can.update },
            ...more(invoice),
        ];
    };

    const invoice = pending?.invoice;
    const dialogs: ReactNode = (
        <>
            {records.dialogs}
            <ConfirmDialog
                open={pending?.kind === 'advance'}
                onClose={close}
                tone="primary"
                onConfirm={() => invoice && advance.mutate(invoice)}
                loading={advance.isPending}
                title={t('filament/resources/purchase_invoice.actions.advance_stage.label', { stage: invoice?.can.next_stage?.label ?? '' })}
                description={t('filament/resources/purchase_invoice.actions.advance_stage.description')}
            />
            <ConfirmDialog
                open={pending?.kind === 'revert'}
                onClose={close}
                onConfirm={() => invoice && revert.mutate(invoice)}
                loading={revert.isPending}
                title={t('filament/resources/purchase_invoice.actions.revert_confirmation.label')}
                description={t('filament/resources/purchase_invoice.actions.revert_confirmation.description')}
            />
            <ConfirmDialog
                open={pending?.kind === 'confirm'}
                onClose={close}
                tone="primary"
                onConfirm={() => invoice && confirm.mutate({ invoice, amount })}
                loading={confirm.isPending}
                title={t('filament/resources/purchase_invoice.actions.confirm.heading', { side: invoice?.awaited_confirmation?.label ?? '' })}
                description={t('filament/resources/purchase_invoice.actions.confirm.description', { amount: yuan(invoice?.amount) })}
                confirmLabel={t('filament/resources/purchase_invoice.actions.confirm.submit')}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (invoice) confirm.mutate({ invoice, amount });
                    }}
                >
                    <Field label={t('filament/resources/purchase_invoice.actions.confirm.amount')} error={amountError} required>
                        {(id) => (
                            <div className="relative">
                                <MoneyInput id={id} autoFocus value={amount} onChange={setAmount} invalid={Boolean(amountError)} className="pe-8" />
                                <span className="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-sm text-faint">¥</span>
                            </div>
                        )}
                    </Field>
                </form>
            </ConfirmDialog>
        </>
    );

    return { items, steps, more, dialogs };
}
