import type { ReactNode } from 'react';
import { cn } from '../../lib/format';

export type Tone = 'gray' | 'accent' | 'success' | 'warning' | 'danger' | 'info';

const tones: Record<Tone, string> = {
    gray: 'bg-subtle text-muted ring-line',
    accent: 'bg-accent-soft text-accent ring-accent/15',
    success: 'bg-success-soft text-success ring-success/15',
    warning: 'bg-warning-soft text-warning-ink ring-warning/20',
    danger: 'bg-danger-soft text-danger ring-danger/15',
    info: 'bg-info-soft text-info ring-info/15',
};

const dots: Record<Tone, string> = {
    gray: 'bg-faint',
    accent: 'bg-accent',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
};

/** Filament color names map to the same tones, so a server can send 'success' or 'gray' as it did for the panel. */
export function toneOf(color: string | null | undefined): Tone {
    switch (color) {
        case 'success':
        case 'warning':
        case 'danger':
        case 'info':
        case 'gray':
            return color;
        case 'primary':
            return 'accent';
        default:
            return 'gray';
    }
}

export function Badge({ tone = 'gray', children, className, icon, dot }: { tone?: Tone; children: ReactNode; className?: string; icon?: ReactNode; dot?: boolean }) {
    return (
        <span className={cn('inline-flex h-5.5 items-center gap-1.5 rounded-md px-2 text-[11.5px] font-medium whitespace-nowrap ring-1 ring-inset [&_svg]:size-3', tones[tone], className)}>
            {dot && <span className={cn('size-1.5 rounded-full', dots[tone])} />}
            {icon}
            {children}
        </span>
    );
}
