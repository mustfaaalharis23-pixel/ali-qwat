import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useQuery } from '@tanstack/react-query';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import { api, type Query } from '../lib/api';
import { cn } from '../lib/format';
import { useT } from '../lib/i18n';
import { controlClass, Spinner } from './ui';

export interface SearchOption {
    value: number | string;
    label: string;
    description?: string | null;
}

interface SearchSelectProps {
    /** The API path that answers { data: SearchOption[] } for ?search=, such as /options/customers. */
    endpoint: string;
    value: number | string | null | undefined;
    onChange: (value: number | string | null, option: SearchOption | null) => void;
    placeholder?: string;
    query?: Query;
    disabled?: boolean;
    invalid?: boolean;
    /** The label of the chosen value before the list was searched, such as the customer of an order being edited. */
    initialLabel?: string | null;
    clearable?: boolean;
    id?: string;
}

/** A select that searches the server as the user types, for long lists such as the customers or the items. */
export function SearchSelect({ endpoint, value, onChange, placeholder, query = {}, disabled, invalid, initialLabel, clearable = true, id }: SearchSelectProps) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [highlight, setHighlight] = useState(0);
    const [debounced, setDebounced] = useState('');
    const [chosen, setChosen] = useState<SearchOption | null>(value !== null && value !== undefined && initialLabel ? { value, label: initialLabel } : null);
    const ref = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState<{ top: number; left: number; width: number; above: boolean } | null>(null);

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(search), 250);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        if (value === null || value === undefined || value === '') setChosen(null);
        else if (initialLabel && chosen?.value !== value) setChosen({ value, label: initialLabel });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, initialLabel]);

    useEffect(() => {
        if (!open) return;
        const close = (event: MouseEvent) => !ref.current?.contains(event.target as Node) && !panelRef.current?.contains(event.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    // The list floats above the page, so a card that clips its content never cuts it; it follows the field when the page scrolls.
    useLayoutEffect(() => {
        if (!open) return;
        const place = () => {
            const rect = ref.current?.getBoundingClientRect();
            if (!rect) return;
            const above = window.innerHeight - rect.bottom < 320 && rect.top > 320;
            setPosition({ top: above ? rect.top - 6 : rect.bottom + 4, left: rect.left, width: rect.width, above });
        };
        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
        return () => {
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        };
    }, [open]);

    const { data, isFetching } = useQuery({
        queryKey: ['options', endpoint, debounced, query],
        queryFn: () => api.get<{ data: SearchOption[] }>(endpoint, { ...query, search: debounced }),
        enabled: open,
        staleTime: 30_000,
    });

    const selectedLabel = chosen?.label ?? data?.data.find((option) => option.value === value)?.label;

    return (
        <div ref={ref} className="relative">
            <button
                id={id}
                type="button"
                disabled={disabled}
                aria-invalid={invalid || undefined}
                onClick={() => setOpen((current) => !current)}
                className={cn(controlClass, 'flex h-9 w-full items-center justify-between gap-2 text-start')}
            >
                <span className={cn('truncate', !selectedLabel && 'text-faint')}>{selectedLabel ?? placeholder ?? t('admin.form.choose')}</span>
                <span className="flex items-center gap-1 text-faint">
                    {clearable && value !== null && value !== undefined && value !== '' && !disabled && (
                        <X
                            className="size-4 hover:text-ink"
                            onClick={(event) => {
                                event.stopPropagation();
                                setChosen(null);
                                onChange(null, null);
                            }}
                        />
                    )}
                    <ChevronDown className="size-4" />
                </span>
            </button>
            {open &&
                position &&
                createPortal(
                <div
                    ref={panelRef}
                    style={{ position: 'fixed', top: position.top, left: position.left, width: Math.max(position.width, 240), transform: position.above ? 'translateY(-100%)' : undefined }}
                    className="animate-in z-[60] rounded-xl border border-line bg-surface p-1 shadow-[var(--shadow-pop)]"
                    dir={document.documentElement.dir}
                >
                    <div className="relative mb-1">
                        <Search className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-faint" />
                        <input
                            autoFocus
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setHighlight(0);
                            }}
                            onKeyDown={(event) => {
                                const options = data?.data ?? [];
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    setHighlight((index) => Math.min(options.length - 1, index + 1));
                                } else if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    setHighlight((index) => Math.max(0, index - 1));
                                } else if (event.key === 'Enter') {
                                    event.preventDefault();
                                    const option = options[highlight];
                                    if (option) {
                                        setChosen(option);
                                        onChange(option.value, option);
                                        setOpen(false);
                                        setSearch('');
                                    }
                                } else if (event.key === 'Escape') {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    setOpen(false);
                                }
                            }} placeholder={t('admin.table.search')} className="h-9 w-full rounded-lg bg-subtle ps-8 pe-3 text-sm outline-none" />
                    </div>
                    <div className="max-h-64 overflow-y-auto">
                        {isFetching && !data && (
                            <div className="flex justify-center py-4 text-faint">
                                <Spinner className="size-4" />
                            </div>
                        )}
                        {data?.data.length === 0 && <p className="px-3 py-3 text-center text-[13px] text-muted">{t('admin.table.empty')}</p>}
                        {data?.data.map((option, index) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => {
                                    setChosen(option);
                                    onChange(option.value, option);
                                    setOpen(false);
                                    setSearch('');
                                }}
                                onMouseEnter={() => setHighlight(index)}
                                className={cn('flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-start text-[13px]', index === highlight ? 'bg-hover' : '', option.value === value && 'text-accent')}
                            >
                                <span className="min-w-0">
                                    <span className="block truncate">{option.label}</span>
                                    {option.description && <span className="block truncate text-xs text-muted">{option.description}</span>}
                                </span>
                                {option.value === value && <Check className="size-4 shrink-0 text-accent" />}
                            </button>
                        ))}
                    </div>
                </div>,
                ref.current?.closest('dialog') ?? document.body,
                )}
        </div>
    );
}
