import { useState } from 'react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { api } from '../../lib/api';
import { useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Button, ConfirmDialog, toast } from '../../components/ui';

export type BulkKind = 'delete' | 'restore' | 'force_delete';

/**
 * The bulk actions of a sales list (the panel's BulkActionGroup): the buttons over the chosen rows, each asking first,
 * then one request to the list's bulk endpoint, which checks every chosen record before changing any.
 */
export function useBulkActions(endpoint: string, allowed: Partial<Record<BulkKind, boolean>>) {
    const t = useT();
    const [pending, setPending] = useState<{ kind: BulkKind; ids: (string | number)[]; clear: () => void } | null>(null);
    const run = useSave((request: { kind: BulkKind; ids: (string | number)[] }) => api.post<{ count: number; skipped?: number }>(`${endpoint}/bulk`, { action: request.kind, ids: request.ids }), {
        invalidate: ['list', 'record', 'sales', 'metric', 'options'],
        onSuccess: (result) => {
            pending?.clear();
            setPending(null);
            toast.success(t('admin/sales.bulk.done', { count: result.count }), result.skipped ? t('admin/sales.bulk.skipped_with_payments', { count: result.skipped }) : undefined);
        },
    });
    const label = (kind: BulkKind) => (kind === 'restore' ? t('admin.actions.restore') : kind === 'force_delete' ? t('admin.actions.force_delete') : t('admin.actions.delete'));

    return {
        selectable: Boolean(allowed.delete || allowed.restore || allowed.force_delete),
        buttons: (ids: (string | number)[], clear: () => void) => (
            <>
                {allowed.restore && (
                    <Button size="sm" variant="secondary" icon={<RotateCcw className="size-3.5" />} onClick={() => setPending({ kind: 'restore', ids, clear })}>
                        {label('restore')}
                    </Button>
                )}
                {allowed.delete && (
                    <Button size="sm" variant="danger" icon={<Trash2 className="size-3.5" />} onClick={() => setPending({ kind: 'delete', ids, clear })}>
                        {label('delete')}
                    </Button>
                )}
                {allowed.force_delete && (
                    <Button size="sm" variant="danger" icon={<Trash2 className="size-3.5" />} onClick={() => setPending({ kind: 'force_delete', ids, clear })}>
                        {label('force_delete')}
                    </Button>
                )}
            </>
        ),
        dialog: (
            <ConfirmDialog
                open={pending !== null}
                onClose={() => setPending(null)}
                onConfirm={() => pending && run.mutate({ kind: pending.kind, ids: pending.ids })}
                loading={run.isPending}
                tone={pending?.kind === 'restore' ? 'primary' : 'danger'}
                title={pending ? `${label(pending.kind)}: ${t('admin.table.selected', { count: pending.ids.length })}` : ''}
                description={pending?.kind === 'delete' ? t('admin.confirm.delete_description') : pending?.kind === 'force_delete' ? t('admin.confirm.force_delete_description') : t('admin.confirm.are_you_sure')}
                confirmLabel={pending ? label(pending.kind) : undefined}
            />
        ),
    };
}
