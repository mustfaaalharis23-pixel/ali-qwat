import { useEffect, type ReactNode } from 'react';
import { Link } from 'react-router';
import { ChevronRight } from 'lucide-react';
import { boot } from '../lib/i18n';

export interface Crumb {
    label: ReactNode;
    to?: string;
}

/** The top of a page: where it is, its title, a line under it, and its actions. It also names the browser tab. */
export function PageHeader({ title, description, actions, breadcrumbs = [], badge }: { title: ReactNode; description?: ReactNode; actions?: ReactNode; breadcrumbs?: Crumb[]; badge?: ReactNode }) {
    useEffect(() => {
        if (typeof title === 'string') document.title = `${title} · ${boot.appName}`;
    }, [title]);

    return (
        <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                {breadcrumbs.length > 0 && (
                    <nav className="mb-2 flex flex-wrap items-center gap-1 text-[12.5px] text-faint">
                        {breadcrumbs.map((crumb, index) => (
                            <span key={index} className="inline-flex items-center gap-1">
                                {crumb.to ? (
                                    <Link to={crumb.to} className="transition-colors hover:text-ink">
                                        {crumb.label}
                                    </Link>
                                ) : (
                                    <span className="text-muted">{crumb.label}</span>
                                )}
                                {index < breadcrumbs.length - 1 && <ChevronRight className="size-3.5 opacity-60 rtl:rotate-180" />}
                            </span>
                        ))}
                    </nav>
                )}
                <div className="flex flex-wrap items-center gap-2.5">
                    <h1 className="text-[24px] leading-8 font-semibold tracking-[-0.015em]">{title}</h1>
                    {badge}
                </div>
                {description && <p className="mt-1.5 max-w-3xl text-[13.5px] leading-6 text-muted">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
