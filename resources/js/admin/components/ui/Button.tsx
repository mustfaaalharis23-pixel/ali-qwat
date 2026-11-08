import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { Link } from 'react-router';
import { cn } from '../../lib/format';
import { Spinner } from './Spinner';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success' | 'soft';
type Size = 'xs' | 'sm' | 'md';

const variants: Record<Variant, string> = {
    primary:
        'bg-accent text-white shadow-[inset_0_1px_0_oklch(1_0_0/0.15),0_1px_2px_oklch(0.3_0.15_266/0.3)] hover:bg-accent-strong active:translate-y-px',
    secondary: 'bg-surface text-ink border border-line shadow-[var(--shadow-card)] hover:bg-subtle hover:border-line-strong active:translate-y-px',
    soft: 'bg-accent-soft text-accent hover:bg-accent-soft/70',
    ghost: 'text-muted hover:text-ink hover:bg-hover',
    danger: 'bg-danger text-white shadow-[inset_0_1px_0_oklch(1_0_0/0.15)] hover:opacity-90 active:translate-y-px',
    success: 'bg-success text-white shadow-[inset_0_1px_0_oklch(1_0_0/0.15)] hover:opacity-90 active:translate-y-px',
};

const sizes: Record<Size, string> = {
    xs: 'h-7 px-2 text-xs gap-1 rounded-md',
    sm: 'h-8 px-2.5 text-[13px] gap-1.5 rounded-lg',
    md: 'h-9 px-3.5 text-[13.5px] gap-2 rounded-lg',
};

export function buttonClass(variant: Variant = 'secondary', size: Size = 'md', extra?: string) {
    return cn(
        'inline-flex items-center justify-center font-medium whitespace-nowrap transition-[background-color,border-color,color,transform] duration-150 disabled:opacity-50 disabled:pointer-events-none select-none [&_svg]:shrink-0',
        variants[variant],
        sizes[size],
        extra,
    );
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    icon?: ReactNode;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    { variant = 'secondary', size = 'md', loading, icon, className, children, disabled, type = 'button', ...props },
    ref,
) {
    return (
        <button ref={ref} type={type} className={buttonClass(variant, size, className)} disabled={disabled || loading} {...props}>
            {loading ? <Spinner className="size-4" /> : icon}
            {children}
        </button>
    );
});

export function ButtonLink({ to, variant = 'secondary', size = 'md', icon, className, children, ...props }: { to: string; variant?: Variant; size?: Size; icon?: ReactNode; className?: string; children?: ReactNode; target?: string }) {
    // Print pages and files are Laravel pages outside the application; everything else is a screen of the application.
    const external = /^(https?:|\/\/|\/admin-api\/|\/invoices\/\d+\/print|\/purchase-invoices\/\d+\/(print|label)|\/customer-payments\/\d+\/receipt|\/storage\/)/.test(to);
    if (external) {
        return (
            <a href={to} className={buttonClass(variant, size, className)} {...props}>
                {icon}
                {children}
            </a>
        );
    }
    return (
        <Link to={to} className={buttonClass(variant, size, className)} {...props}>
            {icon}
            {children}
        </Link>
    );
}
