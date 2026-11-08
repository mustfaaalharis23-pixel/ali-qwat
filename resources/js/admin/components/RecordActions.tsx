import { useState, type ReactNode } from 'react';
import { Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { api } from '../lib/api';
import { useSave } from '../lib/form';
import { useT } from '../lib/i18n';
import { ConfirmDialog, type MenuItem } from './ui';

export interface Abilities {
    view?: boolean;
    update?: boolean;
    delete?: boolean;
    restore?: boolean;
    force_delete?: boolean;
}

/**
 * The delete, restore, and delete-forever actions of the records of one API resource, with their confirmations.
 * endpoint is the resource's path (/colors); a record is deleted at /colors/{id}, restored at /colors/{id}/restore,
 * and deleted forever at /colors/{id}/force. Every list and page that shows the resource's records is read again after.
 */
export function useRecordActions<T extends { id: number; can?: Abilities }>(endpoint: string, options: { onDeleted?: () => void; label?: (record: T) => string } = {}) {
    const t = useT();
    const [pending, setPending] = useState<{ kind: 'delete' | 'force'; record: T } | null>(null);
    const invalidate = ['list', 'record', 'options', endpoint];

    const remove = useSave((record: T) => api.delete(`${endpoint}/${record.id}`), { invalidate, success: t('admin.actions.deleted'), onSuccess: () => { setPending(null); options.onDeleted?.(); } });
    const force = useSave((record: T) => api.delete(`${endpoint}/${record.id}/force`), { invalidate, success: t('admin.actions.deleted'), onSuccess: () => { setPending(null); options.onDeleted?.(); } });
    const restore = useSave((record: T) => api.post(`${endpoint}/${record.id}/restore`), { invalidate, success: t('admin.actions.restored') });

    const items = (record: T, extra: { edit?: () => void } = {}): MenuItem[] => [
        { label: t('admin.actions.edit'), icon: <Pencil className="size-4" />, onSelect: extra.edit, hidden: !extra.edit || !record.can?.update },
        { label: t('admin.actions.restore'), icon: <RotateCcw className="size-4" />, onSelect: () => restore.mutate(record), hidden: !record.can?.restore },
        { label: t('admin.actions.delete'), icon: <Trash2 className="size-4" />, tone: 'danger', onSelect: () => setPending({ kind: 'delete', record }), hidden: !record.can?.delete },
        { label: t('admin.actions.force_delete'), icon: <Trash2 className="size-4" />, tone: 'danger', onSelect: () => setPending({ kind: 'force', record }), hidden: !record.can?.force_delete },
    ];

    const dialogs: ReactNode = (
        <ConfirmDialog
            open={pending !== null}
            onClose={() => setPending(null)}
            onConfirm={() => pending && (pending.kind === 'delete' ? remove : force).mutate(pending.record)}
            loading={remove.isPending || force.isPending}
            title={pending && options.label ? `${t('admin.actions.delete')}: ${options.label(pending.record)}` : t('admin.confirm.delete')}
            description={pending?.kind === 'force' ? t('admin.confirm.force_delete_description') : t('admin.confirm.delete_description')}
            confirmLabel={pending?.kind === 'force' ? t('admin.actions.force_delete') : t('admin.actions.delete')}
        />
    );

    return { items, dialogs, restore, remove };
}
