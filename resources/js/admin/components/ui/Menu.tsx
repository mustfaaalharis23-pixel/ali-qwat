import { useEffect, useRef, useState, type ReactNode } from 'react';
import { cn } from '../../lib/format';

export interface MenuItem {
    label: ReactNode;
    icon?: ReactNode;
    onSelect?: () => void;
    href?: string;
    tone?: 'danger';
    hidden?: boolean;
    disabled?: boolean;
    /** A thin line between groups of items. */
    divider?: boolean;
}

/** A dropdown of actions under (or above) a trigger, closed by a click outside or Escape. */
export function Menu({ trigger, items, align = 'end', placement = 'bottom' }: { trigger: (toggle: () => void, open: boolean) => ReactNode; items: MenuItem[]; align?: 'start' | 'end'; placement?: 'bottom' | 'top' }) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const close = (event: MouseEvent | KeyboardEvent) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !ref.current?.contains(event.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);
        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    const visible = items.filter((item) => !item.hidden).filter((item, index, list) => !item.divider || (index > 0 && index < list.length - 1 && !list[index - 1].divider));

    return (
        <div ref={ref} className="relative">
            {trigger(() => setOpen((value) => !value), open)}
            {open && visible.some((item) => !item.divider) && (
                <div
                    className={cn(
                        'animate-in absolute z-40 min-w-52 rounded-xl border border-line bg-surface p-1 shadow-[var(--shadow-pop)]',
                        placement === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5',
                        align === 'end' ? 'end-0' : 'start-0',
                        placement === 'top' && 'w-full',
                    )}
                >
                    {visible.map((item, index) => {
                        if (item.divider) return <div key={index} className="mx-2 my-1 h-px bg-line" />;
                        const className = cn(
                            'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.75 text-start text-[13px] transition-colors disabled:opacity-60',
                            item.tone === 'danger' ? 'text-danger hover:bg-danger-soft' : 'text-ink hover:bg-hover',
                        );
                        return item.href ? (
                            <a key={index} href={item.href} target="_blank" rel="noreferrer" className={className} onClick={() => setOpen(false)}>
                                {item.icon}
                                {item.label}
                            </a>
                        ) : (
                            <button
                                key={index}
                                className={className}
                                disabled={item.disabled}
                                onClick={() => {
                                    setOpen(false);
                                    item.onSelect?.();
                                }}
                            >
                                {item.icon}
                                {item.label}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
