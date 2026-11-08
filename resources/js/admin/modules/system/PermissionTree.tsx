import { useEffect, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '../../lib/format';

export interface PermissionGroup {
    key: string;
    label: string;
    permissions: { name: string; label: string }[];
}

function GroupCheckbox({ checked, indeterminate, disabled, onChange }: { checked: boolean; indeterminate: boolean; disabled?: boolean; onChange: () => void }) {
    const ref = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (ref.current) ref.current.indeterminate = indeterminate;
    }, [indeterminate]);
    return <input ref={ref} type="checkbox" className="size-4 accent-[var(--color-accent)]" checked={checked} disabled={disabled} onChange={onChange} />;
}

/**
 * A tree of checkboxes: one parent checkbox per group that selects all of its permissions, with the permissions nested under it.
 * The value is a flat list of the selected permission names.
 */
export function PermissionTree({ groups, value, onChange, disabled }: { groups: PermissionGroup[]; value: string[]; onChange?: (value: string[]) => void; disabled?: boolean }) {
    const [collapsed, setCollapsed] = useState<string[]>([]);
    const has = (permission: string) => value.includes(permission);

    const toggle = (permission: string) => onChange?.(has(permission) ? value.filter((selected) => selected !== permission) : [...value, permission]);
    const toggleGroup = (names: string[]) => {
        const others = value.filter((selected) => !names.includes(selected));
        onChange?.(names.every(has) ? others : [...others, ...names]);
    };

    return (
        <div className="columns-1 gap-3 md:columns-2 xl:columns-3">
            {groups.map((group) => {
                const names = group.permissions.map((permission) => permission.name);
                const count = names.filter(has).length;
                const open = !collapsed.includes(group.key);
                return (
                    <div key={group.key} className="mb-3 break-inside-avoid rounded-xl border border-line bg-surface p-3 shadow-[var(--shadow-card)]">
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setCollapsed((list) => (open ? [...list, group.key] : list.filter((key) => key !== group.key)))}
                                aria-expanded={open}
                                className="rounded p-0.5 text-faint hover:bg-subtle hover:text-ink"
                            >
                                <ChevronDown className={cn('size-4 transition-transform', !open && '-rotate-90 rtl:rotate-90')} />
                            </button>
                            <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 text-sm font-semibold">
                                <GroupCheckbox checked={count === names.length && names.length > 0} indeterminate={count > 0 && count < names.length} disabled={disabled} onChange={() => toggleGroup(names)} />
                                <span className="truncate">{group.label}</span>
                            </label>
                            <span className="text-xs text-faint tabular-nums">
                                {count} / {names.length}
                            </span>
                        </div>
                        {open && (
                            <div className="ms-3 mt-1.5 grid gap-1 border-s border-line ps-5">
                                {group.permissions.map((permission) => (
                                    <label key={permission.name} className="flex cursor-pointer items-center gap-2.5 py-0.5 text-sm">
                                        <input type="checkbox" className="size-4 accent-[var(--color-accent)]" checked={has(permission.name)} disabled={disabled} onChange={() => toggle(permission.name)} />
                                        <span>{permission.label}</span>
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
