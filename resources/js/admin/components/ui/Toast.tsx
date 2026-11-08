import { useSyncExternalStore } from 'react';
import { CheckCircle2, CircleAlert, Info, X } from 'lucide-react';
import { cn } from '../../lib/format';

type Kind = 'success' | 'error' | 'info';
interface ToastItem {
    id: number;
    kind: Kind;
    title: string;
    body?: string;
}

let items: ToastItem[] = [];
let next = 1;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((listener) => listener());

function push(kind: Kind, title: string, body?: string) {
    const id = next++;
    items = [...items, { id, kind, title, body }];
    emit();
    setTimeout(() => dismiss(id), kind === 'error' ? 7000 : 4000);
}

function dismiss(id: number) {
    items = items.filter((item) => item.id !== id);
    emit();
}

/** Short notifications at the corner of the screen. */
export const toast = {
    success: (title: string, body?: string) => push('success', title, body),
    error: (title: string, body?: string) => push('error', title, body),
    info: (title: string, body?: string) => push('info', title, body),
};

const icons = { success: CheckCircle2, error: CircleAlert, info: Info };
const colors = { success: 'text-success', error: 'text-danger', info: 'text-info' };

export function Toaster() {
    const list = useSyncExternalStore(
        (listener) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        () => items,
    );

    return (
        <div className="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:end-4 sm:w-96" aria-live="polite">
            {list.map((item) => {
                const Icon = icons[item.kind];
                return (
                    <div key={item.id} className="pointer-events-auto flex w-full items-start gap-3 rounded-xl border border-line bg-surface p-3.5 shadow-[var(--shadow-pop)]">
                        <Icon className={cn('mt-0.5 size-4.5 shrink-0', colors[item.kind])} />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium">{item.title}</p>
                            {item.body && <p className="mt-0.5 text-[13px] text-muted">{item.body}</p>}
                        </div>
                        <button className="text-faint hover:text-ink" onClick={() => dismiss(item.id)}>
                            <X className="size-4" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
