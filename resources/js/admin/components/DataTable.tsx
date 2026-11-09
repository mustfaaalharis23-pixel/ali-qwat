import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { ArrowDown, ArrowUp, Check, ChevronLeft, ChevronRight, ChevronsUpDown, Columns3, Ellipsis, Search, X } from 'lucide-react';
import { api, type Paginated, type Query } from '../lib/api';
import { cn, number } from '../lib/format';
import { useI18n } from '../lib/i18n';
import { Button, Empty, Input, Menu, Select, Skeleton, type MenuItem, type Option } from './ui';

export interface Column<T> {
    key: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    sortable?: boolean;
    align?: 'start' | 'end' | 'center';
    className?: string;
    hidden?: boolean;
}

export interface Filter {
    key: string;
    label: string;
    options: Option[];
}

interface DataTableProps<T> {
    /** The API path of the list, such as /customers. */
    endpoint: string;
    columns: Column<T>[];
    filters?: Filter[];
    searchable?: boolean;
    /** Shows the deleted-records filter (with or only the deleted ones). */
    trashed?: boolean;
    defaultSort?: { column: string; direction: 'asc' | 'desc' };
    rowHref?: (row: T) => string | null;
    rowActions?: (row: T) => MenuItem[];
    /** Buttons at the end of the toolbar; a function gets the list's answer, such as its can.create. */
    toolbar?: ReactNode | ((response: (Paginated<T> & Record<string, unknown>) | undefined) => ReactNode);
    /** Query values the page always sends, such as the customer of a nested list. */
    query?: Query;
    emptyTitle?: ReactNode;
    /** The key of the list's state in the address, so two lists on one page keep their own. */
    stateKey?: string;
    perPageOptions?: number[];
    rowKey?: (row: T) => string | number;
    /** Something to show under the table, from the extra data the list answered with. */
    footer?: (response: Paginated<T> & Record<string, unknown>) => ReactNode;
    className?: string;
    selectable?: boolean;
    bulkActions?: (selected: (string | number)[], clear: () => void) => ReactNode;
    rowClassName?: (row: T) => string | undefined;
}

/**
 * A list read page by page from the API: search, sort, filters, and the page live in the address, so a list
 * can be shared and survives a reload. Every list of the application is this one table.
 */
export function DataTable<T extends { id?: number | string }>({
    endpoint,
    columns,
    filters = [],
    searchable = true,
    trashed = false,
    defaultSort,
    rowHref,
    rowActions,
    toolbar,
    query = {},
    emptyTitle,
    stateKey = '',
    perPageOptions = [10, 25, 50, 100],
    rowKey = (row) => row.id as number,
    footer,
    className,
    selectable,
    bulkActions,
    rowClassName,
}: DataTableProps<T>) {
    const { t } = useI18n();
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const key = (name: string) => (stateKey ? `${stateKey}_${name}` : name);
    const read = (name: string) => params.get(key(name)) ?? '';

    const [searchText, setSearchText] = useState(read('search'));
    const [selected, setSelected] = useState<(string | number)[]>([]);

    const state = {
        search: read('search'),
        sort: read('sort') || defaultSort?.column || '',
        direction: (read('direction') || defaultSort?.direction || 'asc') as 'asc' | 'desc',
        page: Number(read('page') || 1),
        perPage: Number(read('per_page') || 25),
        trashed: read('trashed'),
        filters: Object.fromEntries(filters.map((filter) => [filter.key, read(`f_${filter.key}`)])),
    };

    const update = (changes: Record<string, string | number | null>, resetPage = true) => {
        setParams(
            (current) => {
                const nextParams = new URLSearchParams(current);
                for (const [name, value] of Object.entries(changes)) {
                    if (value === null || value === '' || value === undefined) nextParams.delete(key(name));
                    else nextParams.set(key(name), String(value));
                }
                if (resetPage && !('page' in changes)) nextParams.delete(key('page'));
                return nextParams;
            },
            { replace: true },
        );
        setSelected([]);
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchText !== state.search) update({ search: searchText });
        }, 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchText]);

    const requestQuery: Query = {
        ...query,
        search: state.search || undefined,
        sort: state.sort || undefined,
        direction: state.sort ? state.direction : undefined,
        page: state.page,
        per_page: state.perPage,
        trashed: state.trashed || undefined,
        filter: { ...((query.filter as Record<string, unknown> | undefined) ?? {}), ...Object.fromEntries(Object.entries(state.filters).filter(([, value]) => value !== '')) },
    };

    const { data, isLoading, isFetching, isError, error } = useQuery({
        queryKey: ['list', endpoint, requestQuery],
        queryFn: () => api.get<Paginated<T> & Record<string, unknown>>(endpoint, requestQuery),
        placeholderData: keepPreviousData,
    });

    const columnsKey = `admin-columns:${endpoint}:${stateKey}`;
    const [hiddenKeys, setHiddenKeys] = useState<string[]>(() => {
        try {
            return JSON.parse(localStorage.getItem(columnsKey) ?? '[]');
        } catch {
            return [];
        }
    });
    const toggleColumn = (key: string) =>
        setHiddenKeys((current) => {
            const next = current.includes(key) ? current.filter((item) => item !== key) : [...current, key];
            try {
                localStorage.setItem(columnsKey, JSON.stringify(next));
            } catch {
                /* remembered only when the browser allows it */
            }
            return next;
        });
    const availableColumns = useMemo(() => columns.filter((column) => !column.hidden), [columns]);
    const visibleColumns = useMemo(() => availableColumns.filter((column) => !hiddenKeys.includes(column.key)), [availableColumns, hiddenKeys]);
    const rows = data?.data ?? [];
    const meta = data?.meta;
    const activeFilters = Object.values(state.filters).filter(Boolean).length + (state.trashed ? 1 : 0);
    const allSelected = rows.length > 0 && rows.every((row) => selected.includes(rowKey(row)));

    const toggleSort = (column: string) => {
        if (state.sort !== column) update({ sort: column, direction: 'asc' });
        else update({ sort: column, direction: state.direction === 'asc' ? 'desc' : 'asc' });
    };

    const align = (value?: 'start' | 'end' | 'center') => (value === 'end' ? 'text-end' : value === 'center' ? 'text-center' : 'text-start');

    return (
        <div className={cn('card animate-in overflow-hidden', className)}>
            {(searchable || filters.length > 0 || trashed || toolbar) && (
                <div className="flex flex-wrap items-center gap-2 border-b border-line px-3 py-2.5">
                    {searchable && (
                        <div className="relative min-w-48 flex-1 sm:max-w-72">
                            <Search className="pointer-events-none absolute start-3 top-1/2 size-3.75 -translate-y-1/2 text-faint" />
                            <Input value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder={t('admin.table.search')} className="h-8.5 w-full border-transparent bg-subtle ps-8.5 shadow-none hover:border-line focus:bg-surface" />
                        </div>
                    )}
                    {filters.map((filter) => (
                        <Select
                            key={filter.key}
                            value={state.filters[filter.key]}
                            onChange={(event) => update({ [`f_${filter.key}`]: event.target.value })}
                            options={filter.options}
                            placeholder={filter.label}
                            className={cn('h-8.5 w-auto max-w-56 text-[13px]', state.filters[filter.key] && 'border-accent/40 bg-accent-soft text-accent')}
                        />
                    ))}
                    {trashed && (
                        <Select
                            value={state.trashed}
                            onChange={(event) => update({ trashed: event.target.value })}
                            options={[
                                { value: 'with', label: t('admin.table.trashed_with') },
                                { value: 'only', label: t('admin.table.trashed_only') },
                            ]}
                            placeholder={t('admin.table.trashed_without')}
                            className={cn('h-8.5 w-auto text-[13px]', state.trashed && 'border-accent/40 bg-accent-soft text-accent')}
                        />
                    )}
                    {activeFilters > 0 && (
                        <Button variant="ghost" size="sm" icon={<X className="size-3.5" />} onClick={() => update({ trashed: null, ...Object.fromEntries(filters.map((filter) => [`f_${filter.key}`, null])) })}>
                            {t('admin.table.clear_filters')}
                        </Button>
                    )}
                    <div className="ms-auto flex flex-wrap items-center gap-2">
                        {selectable && selected.length > 0 && bulkActions?.(selected, () => setSelected([]))}
                        {availableColumns.length > 3 && (
                            <Menu
                                items={availableColumns.map((column) => ({
                                    label: (
                                        <span className="flex flex-1 items-center justify-between gap-4">
                                            <span className="truncate">{column.header}</span>
                                            {!hiddenKeys.includes(column.key) && <Check className="size-3.5 text-accent" />}
                                        </span>
                                    ),
                                    keepOpen: true,
                                    disabled: !hiddenKeys.includes(column.key) && visibleColumns.length <= 1,
                                    onSelect: () => toggleColumn(column.key),
                                }))}
                                trigger={(toggle) => (
                                    <Button size="sm" variant="ghost" onClick={toggle} icon={<Columns3 className="size-4" />} aria-label={t('admin.table.columns')} title={t('admin.table.columns')}>
                                        {hiddenKeys.filter((key) => availableColumns.some((column) => column.key === key)).length > 0 && (
                                            <span className="num rounded bg-accent-soft px-1 text-[11px] text-accent">{availableColumns.length - visibleColumns.length}</span>
                                        )}
                                    </Button>
                                )}
                            />
                        )}
                        {typeof toolbar === 'function' ? toolbar(data) : toolbar}
                    </div>
                </div>
            )}

            <div className="relative overflow-x-auto">
                {isFetching && !isLoading && <div className="absolute inset-x-0 top-0 h-0.5 animate-pulse bg-accent/60" />}
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-line bg-subtle/70 text-[12px] text-muted">
                            {selectable && (
                                <th className="w-10 px-3 py-2.5">
                                    <input type="checkbox" className="size-4 accent-[var(--color-accent)]" checked={allSelected} onChange={() => setSelected(allSelected ? [] : rows.map(rowKey))} />
                                </th>
                            )}
                            {visibleColumns.map((column) => (
                                <th key={column.key} className={cn('h-10 px-4 font-medium whitespace-nowrap first:ps-5 last:pe-5', align(column.align), column.className)}>
                                    {column.sortable ? (
                                        <button className={cn('-mx-1 inline-flex items-center gap-1 rounded px-1 py-0.5 transition-colors hover:bg-hover hover:text-ink', state.sort === column.key && 'text-ink')} onClick={() => toggleSort(column.key)}>
                                            {column.header}
                                            {state.sort === column.key ? (
                                                state.direction === 'asc' ? <ArrowUp className="size-3.5" /> : <ArrowDown className="size-3.5" />
                                            ) : (
                                                <ChevronsUpDown className="size-3 opacity-40" />
                                            )}
                                        </button>
                                    ) : (
                                        column.header
                                    )}
                                </th>
                            ))}
                            {rowActions && <th className="w-12" />}
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading &&
                            Array.from({ length: 6 }).map((_, index) => (
                                <tr key={index} className="border-b border-line last:border-0">
                                    {visibleColumns.map((column) => (
                                        <td key={column.key} className="h-12 px-4 first:ps-5">
                                            <Skeleton className="h-3.5 w-2/3" />
                                        </td>
                                    ))}
                                    {rowActions && <td />}
                                </tr>
                            ))}
                        {!isLoading &&
                            rows.map((row) => {
                                const href = rowHref?.(row);
                                const id = rowKey(row);
                                return (
                                    <tr
                                        key={id}
                                        onClick={(event) => {
                                            if (!href || (event.target as HTMLElement).closest('a,button,input,label')) return;
                                            if (event.metaKey || event.ctrlKey) window.open(`/app${href}`, '_blank');
                                            else navigate(href);
                                        }}
                                        className={cn('group/row border-b border-line/70 transition-colors last:border-0', href ? 'cursor-pointer hover:bg-subtle/70' : 'hover:bg-subtle/40', selected.includes(id) && 'bg-accent-soft/50', rowClassName?.(row))}
                                    >
                                        {selectable && (
                                            <td className="px-3 py-3">
                                                <input
                                                    type="checkbox"
                                                    className="size-4 accent-[var(--color-accent)]"
                                                    checked={selected.includes(id)}
                                                    onChange={() => setSelected((list) => (list.includes(id) ? list.filter((value) => value !== id) : [...list, id]))}
                                                />
                                            </td>
                                        )}
                                        {visibleColumns.map((column) => (
                                            <td key={column.key} className={cn('h-12 px-4 py-2 align-middle text-[13.5px] whitespace-nowrap tabular-nums first:ps-5 last:pe-5', align(column.align), column.className)}>
                                                {column.cell(row)}
                                            </td>
                                        ))}
                                        {rowActions && (
                                            <td className="px-2 py-2 text-end">
                                                <Menu
                                                    items={rowActions(row)}
                                                    trigger={(toggle) => (
                                                        <button onClick={toggle} className="rounded-md p-1.5 text-faint opacity-60 transition-opacity group-hover/row:opacity-100 hover:bg-hover hover:text-ink" aria-label={t('admin.table.actions')}>
                                                            <Ellipsis className="size-4" />
                                                        </button>
                                                    )}
                                                />
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                    </tbody>
                </table>
                {!isLoading && rows.length === 0 && (
                    <Empty title={isError ? (error as Error).message : (emptyTitle ?? t('admin.table.empty'))} description={state.search || activeFilters ? t('admin.table.empty_hint') : undefined} />
                )}
            </div>

            {data && footer?.(data)}

            {meta && meta.total > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-subtle/40 px-5 py-2.5 text-[12.5px] text-muted">
                    <span className="tabular-nums">{t('admin.table.showing', { from: number(meta.from), to: number(meta.to), total: number(meta.total) })}</span>
                    <div className="flex items-center gap-2">
                        <Select
                            value={state.perPage}
                            onChange={(event) => update({ per_page: event.target.value })}
                            options={perPageOptions.map((value) => ({ value, label: String(value) }))}
                            className="h-7.5 w-auto text-[12.5px]"
                            aria-label={t('admin.table.per_page')}
                        />
                        <Button size="xs" variant="secondary" className="w-7.5 px-0" disabled={meta.current_page <= 1} onClick={() => update({ page: meta.current_page - 1 }, false)} aria-label={t('admin.table.previous')}>
                            <ChevronLeft className="size-4 rtl:rotate-180" />
                        </Button>
                        <span className="num px-1 whitespace-nowrap">
                            {meta.current_page} / {meta.last_page}
                        </span>
                        <Button size="xs" variant="secondary" className="w-7.5 px-0" disabled={meta.current_page >= meta.last_page} onClick={() => update({ page: meta.current_page + 1 }, false)} aria-label={t('admin.table.next')}>
                            <ChevronRight className="size-4 rtl:rotate-180" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
