import { useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
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
    /** The menu stays open after this item is chosen, as for a list of checkboxes. */
    keepOpen?: boolean;
}

/** A dropdown of actions under (or above) a trigger, closed by a click outside or Escape. */
export function Menu({ trigger, items, align = 'end', placement = 'bottom' }: { trigger: (toggle: () => void, open: boolean) => ReactNode; items: MenuItem[]; align?: 'start' | 'end'; placement?: 'bottom' | 'top' }) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const [rect, setRect] = useState<DOMRect | null>(null);

    // The list floats above the page, so a table or card that clips its content never hides it.
    useLayoutEffect(() => {
        if (!open) return;
        const place = () => setRect(ref.current?.getBoundingClientRect() ?? null);
        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
        return () => {
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        };
    }, [open]);

    useEffect(() => {
        if (!open) return;
        const close = (event: MouseEvent | KeyboardEvent) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !ref.current?.contains(event.target as Node) && !panelRef.current?.contains(event.target as Node)) setOpen(false);
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
            {open &&
                rect &&
                visible.some((item) => !item.divider) &&
                createPortal(
                <div
                    ref={panelRef}
                    dir={document.documentElement.dir}
                    style={(() => {
                        const atEnd = (align === 'end') !== (document.documentElement.dir === 'rtl');
                        const below = placement === 'bottom' && window.innerHeight - rect.bottom > 260;
                        return {
                            position: 'fixed' as const,
                            top: below ? rect.bottom + 6 : undefined,
                            bottom: below ? undefined : window.innerHeight - rect.top + 6,
                            left: atEnd ? undefined : rect.left,
                            right: atEnd ? window.innerWidth - rect.right : undefined,
                            width: placement === 'top' ? rect.width : undefined,
                        };
                    })()}
                    className="animate-in z-[60] max-h-[60vh] min-w-52 overflow-y-auto rounded-xl border border-line bg-surface p-1 shadow-[var(--shadow-pop)]"
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
                                    if (!item.keepOpen) setOpen(false);
                                    item.onSelect?.();
                                }}
                            >
                                {item.icon}
                                {item.label}
                            </button>
                        );
                    })}
                </div>,
                ref.current?.closest('dialog') ?? document.body,
                )}
        </div>
    );
}
