import { useEffect, useRef, useState } from 'react';
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
    const [debounced, setDebounced] = useState('');
    const [chosen, setChosen] = useState<SearchOption | null>(value !== null && value !== undefined && initialLabel ? { value, label: initialLabel } : null);
    const ref = useRef<HTMLDivElement>(null);

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
        const close = (event: MouseEvent) => !ref.current?.contains(event.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
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
            {open && (
                <div className="absolute inset-x-0 top-full z-40 mt-1 rounded-xl border border-line bg-surface p-1 shadow-[var(--shadow-pop)]">
                    <div className="relative mb-1">
                        <Search className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-faint" />
                        <input autoFocus value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('admin.table.search')} className="h-9 w-full rounded-lg bg-subtle ps-8 pe-3 text-sm outline-none" />
                    </div>
                    <div className="max-h-64 overflow-y-auto">
                        {isFetching && !data && (
                            <div className="flex justify-center py-4 text-faint">
                                <Spinner className="size-4" />
                            </div>
                        )}
                        {data?.data.length === 0 && <p className="px-3 py-3 text-center text-[13px] text-muted">{t('admin.table.empty')}</p>}
                        {data?.data.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => {
                                    setChosen(option);
                                    onChange(option.value, option);
                                    setOpen(false);
                                    setSearch('');
                                }}
                                className={cn('flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-start text-sm hover:bg-subtle', option.value === value && 'bg-accent-soft/60')}
                            >
                                <span className="min-w-0">
                                    <span className="block truncate">{option.label}</span>
                                    {option.description && <span className="block truncate text-xs text-muted">{option.description}</span>}
                                </span>
                                {option.value === value && <Check className="size-4 shrink-0 text-accent" />}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
