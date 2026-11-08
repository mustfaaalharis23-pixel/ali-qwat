import type { ReactNode } from 'react';
import { cn } from '../../lib/format';

export interface TabItem {
    value: string;
    label: ReactNode;
    count?: number | string;
}

export function Tabs({ tabs, value, onChange, className }: { tabs: TabItem[]; value: string; onChange: (value: string) => void; className?: string }) {
    return (
        <div className={cn('inline-flex max-w-full gap-1 overflow-x-auto rounded-lg bg-subtle p-1', className)} role="tablist">
            {tabs.map((tab) => (
                <button
                    key={tab.value}
                    role="tab"
                    aria-selected={tab.value === value}
                    onClick={() => onChange(tab.value)}
                    className={cn(
                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors',
                        tab.value === value ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink',
                    )}
                >
                    {tab.label}
                    {tab.count !== undefined && <span className="rounded bg-line/70 px-1.5 text-[11px] text-muted">{tab.count}</span>}
                </button>
            ))}
        </div>
    );
}
