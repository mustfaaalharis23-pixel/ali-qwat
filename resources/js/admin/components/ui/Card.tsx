import type { ReactNode } from 'react';
import { cn } from '../../lib/format';

export function Card({ className, children }: { className?: string; children: ReactNode }) {
    return <div className={cn('card animate-in', className)}>{children}</div>;
}

export function CardHeader({ title, description, actions, icon }: { title: ReactNode; description?: ReactNode; actions?: ReactNode; icon?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3.5">
            <div className="flex min-w-0 items-center gap-3">
                {icon && <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-subtle text-muted ring-1 ring-line [&_svg]:size-4">{icon}</div>}
                <div className="min-w-0">
                    <h3 className="text-[14.5px] leading-5 font-semibold tracking-tight">{title}</h3>
                    {description && <p className="mt-0.5 text-[12.5px] leading-5 text-muted">{description}</p>}
                </div>
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function CardBody({ className, children }: { className?: string; children: ReactNode }) {
    return <div className={cn('p-5', className)}>{children}</div>;
}

/** Label and value pairs, as on a record's page. */
export function DescriptionList({ items, columns = 3 }: { items: { label: ReactNode; value: ReactNode; hidden?: boolean }[]; columns?: 1 | 2 | 3 | 4 }) {
    const grid = { 1: 'sm:grid-cols-1', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-2 lg:grid-cols-3', 4: 'sm:grid-cols-2 lg:grid-cols-4' }[columns];
    return (
        <dl className={cn('grid grid-cols-1 gap-x-8 gap-y-5', grid)}>
            {items
                .filter((item) => !item.hidden)
                .map((item, index) => (
                    <div key={index} className="min-w-0">
                        <dt className="text-[12px] font-medium text-muted">{item.label}</dt>
                        <dd className="mt-1 text-[14px] font-medium break-words text-ink">{item.value ?? <span className="text-faint">—</span>}</dd>
                    </div>
                ))}
        </dl>
    );
}
