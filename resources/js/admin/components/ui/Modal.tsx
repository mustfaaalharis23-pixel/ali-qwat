import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { cn } from '../../lib/format';
import { Button } from './Button';
import { useT } from '../../lib/i18n';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl', '2xl': 'max-w-6xl' };

/** A dialog on the native <dialog>, so focus, Escape, and the backdrop work as the browser does them. */
export function Modal({ open, onClose, title, description, children, footer, size = 'md' }: ModalProps) {
    const ref = useRef<HTMLDialogElement>(null);
    const t = useT();

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(event) => event.target === ref.current && onClose()}
            className={cn('m-auto w-[calc(100%-2rem)] rounded-2xl border border-line bg-surface p-0 text-ink shadow-[var(--shadow-pop)] backdrop:bg-transparent', widths[size])}
        >
            {open && (
                <div className="flex max-h-[85vh] flex-col">
                    <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                        <div>
                            <h2 className="text-base font-semibold">{title}</h2>
                            {description && <p className="mt-1 text-sm text-muted">{description}</p>}
                        </div>
                        <button type="button" onClick={onClose} className="rounded-md p-1 text-faint hover:bg-subtle hover:text-ink" aria-label={t('admin.actions.close')}>
                            <X className="size-4" />
                        </button>
                    </div>
                    {children && <div className="overflow-y-auto px-5 py-4">{children}</div>}
                    {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-line bg-subtle/50 px-5 py-3">{footer}</div>}
                </div>
            )}
        </dialog>
    );
}

interface ConfirmProps {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: ReactNode;
    description?: ReactNode;
    confirmLabel?: ReactNode;
    tone?: 'danger' | 'primary';
    loading?: boolean;
    children?: ReactNode;
}

export function ConfirmDialog({ open, onClose, onConfirm, title, description, confirmLabel, tone = 'danger', loading, children }: ConfirmProps) {
    const t = useT();
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            description={description ?? t('admin.confirm.are_you_sure')}
            size="sm"
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant={tone} onClick={onConfirm} loading={loading}>
                        {confirmLabel ?? t('admin.actions.confirm')}
                    </Button>
                </>
            }
        >
            {children}
        </Modal>
    );
}
