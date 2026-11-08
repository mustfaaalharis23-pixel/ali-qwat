import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { CornerDownLeft, Search } from 'lucide-react';
import { cn } from '../lib/format';
import { useT } from '../lib/i18n';
import { NavIcon } from '../components/ui';
import { useNavigation } from './AppLayout';

/** Opens with ⌘K / Ctrl+K: type part of a screen's name, and Enter goes there. */
export function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
    const t = useT();
    const navigate = useNavigate();
    const { data } = useNavigation();
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) {
            setQuery('');
            setActive(0);
            dialog.showModal();
        }
        if (!open && dialog.open) dialog.close();
    }, [open]);

    const items = useMemo(() => {
        const all = (data?.groups ?? []).flatMap((group) => group.items.map((item) => ({ ...item, group: group.label })));
        const needle = query.trim().toLowerCase();
        return needle ? all.filter((item) => `${item.label} ${item.group ?? ''} ${item.path}`.toLowerCase().includes(needle)) : all;
    }, [data, query]);

    const go = (path: string) => {
        onClose();
        navigate(path);
    };

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(event) => event.target === ref.current && onClose()}
            className="mx-auto mt-[12vh] w-[calc(100%-2rem)] max-w-xl rounded-2xl border border-line bg-surface p-0 text-ink shadow-[var(--shadow-pop)] backdrop:bg-transparent"
        >
            {open && (
                <div>
                    <div className="flex items-center gap-3 border-b border-line px-4">
                        <Search className="size-4.5 text-faint" />
                        <input
                            autoFocus
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActive(0);
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    setActive((index) => Math.min(items.length - 1, index + 1));
                                } else if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    setActive((index) => Math.max(0, index - 1));
                                } else if (event.key === 'Enter' && items[active]) {
                                    go(items[active].path);
                                }
                            }}
                            placeholder={t('admin.layout.command_placeholder')}
                            className="h-13 flex-1 bg-transparent text-[15px] outline-none placeholder:text-faint"
                        />
                        <kbd className="rounded border border-line px-1.5 py-0.5 text-[11px] text-faint">Esc</kbd>
                    </div>
                    <ul className="max-h-[50vh] overflow-y-auto p-1.5">
                        {items.length === 0 && <li className="px-3 py-8 text-center text-[13px] text-muted">{t('admin.table.empty')}</li>}
                        {items.map((item, index) => (
                            <li key={item.key}>
                                <button
                                    onMouseEnter={() => setActive(index)}
                                    onClick={() => go(item.path)}
                                    className={cn('flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-start text-[13.5px]', index === active ? 'bg-accent-soft text-accent' : 'text-ink')}
                                >
                                    <NavIcon name={item.icon} className="size-4 opacity-80" />
                                    <span className="flex-1 truncate font-medium">{item.label}</span>
                                    {item.group && <span className="text-xs text-faint">{item.group}</span>}
                                    {index === active && <CornerDownLeft className="size-3.5" />}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </dialog>
    );
}
