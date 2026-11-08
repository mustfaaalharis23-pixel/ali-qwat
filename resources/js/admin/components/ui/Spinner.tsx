import { cn } from '../../lib/format';

export function Spinner({ className }: { className?: string }) {
    return (
        <svg className={cn('animate-spin text-current', className ?? 'size-5')} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.2" strokeWidth="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

export function PageSpinner() {
    return (
        <div className="flex items-center justify-center py-24 text-faint">
            <Spinner className="size-6" />
        </div>
    );
}

export function Skeleton({ className }: { className?: string }) {
    return <div className={cn('animate-pulse rounded-md bg-subtle', className)} />;
}
