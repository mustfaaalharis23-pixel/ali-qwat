import { forwardRef, useId, useLayoutEffect, useRef, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cn } from '../../lib/format';

export const controlClass =
    'rounded-lg border border-line bg-surface px-3 text-[13.5px] text-ink placeholder:text-faint shadow-[var(--shadow-card)] transition-[border-color,box-shadow] duration-150 hover:border-line-strong focus:border-accent focus:outline-none focus:ring-4 focus:ring-[var(--color-accent-ring)] disabled:bg-subtle disabled:text-muted disabled:shadow-none aria-invalid:border-danger aria-invalid:focus:ring-danger/15';

interface FieldProps {
    label?: ReactNode;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    className?: string;
    children: (id: string) => ReactNode;
}

/** A form field: its label, the control, a hint under it, and its error. */
export function Field({ label, error, hint, required, className, children }: FieldProps) {
    const id = useId();
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            {label && (
                <label htmlFor={id} className="text-[13px] font-medium text-ink/90">
                    {label}
                    {required && <span className="ms-0.5 text-danger">*</span>}
                </label>
            )}
            {children(id)}
            {hint && !error && <p className="text-xs text-muted">{hint}</p>}
            {error && <p className="text-xs text-danger">{error}</p>}
        </div>
    );
}

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }>(function Input({ className, invalid, ...props }, ref) {
    return <input ref={ref} aria-invalid={invalid || undefined} className={cn(controlClass, 'h-9', className?.match(/(^|\s)w-/) ? '' : 'w-full', className)} {...props} />;
});

/** A typed amount as a plain number: digits and one point only, no leading zeros, and at most two decimals. */
function plainAmount(typed: string): string {
    const [whole, ...decimals] = typed.replace(/[^\d.]/g, '').split('.');
    const digits = whole.replace(/^0+(?=\d)/, '');
    return decimals.length ? `${digits}.${decimals.join('').slice(0, 2)}` : digits;
}

/** An amount with thousands separators: 1500000.5 shows as 1,500,000.5. */
function groupedAmount(amount: string): string {
    const [whole, ...decimals] = amount.replace(/,/g, '').split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return decimals.length ? `${grouped}.${decimals.join('')}` : grouped;
}

/**
 * The money field of the forms, as the panel's: while typing, the amount gets thousands separators (1,500,000.50) and at most
 * two decimals. The value it hands over has no separators, so the server always gets a plain number.
 */
export function MoneyInput({ value, onChange, ...props }: Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange'> & { value: string | number | null | undefined; onChange: (value: string) => void; invalid?: boolean }) {
    const input = useRef<HTMLInputElement>(null);
    const digitsBeforeCaret = useRef<number | null>(null);

    // The separators move as the amount grows, so the caret is put back after the same digits it was after.
    useLayoutEffect(() => {
        const element = input.current;
        if (element === null || digitsBeforeCaret.current === null) return;
        let position = 0;
        for (let seen = 0; position < element.value.length && seen < digitsBeforeCaret.current; position++) {
            if (element.value[position] !== ',') seen++;
        }
        element.setSelectionRange(position, position);
        digitsBeforeCaret.current = null;
    });

    return (
        <Input
            ref={input}
            inputMode="decimal"
            dir="ltr"
            autoComplete="off"
            {...props}
            value={groupedAmount(String(value ?? ''))}
            onChange={(event) => {
                const typed = event.target.value;
                const plain = plainAmount(typed);
                digitsBeforeCaret.current = Math.min(typed.slice(0, event.target.selectionStart ?? typed.length).replace(/[^\d.]/g, '').length, plain.length);
                onChange(plain);
            }}
        />
    );
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement> & { invalid?: boolean }>(function Textarea({ className, invalid, ...props }, ref) {
    return <textarea ref={ref} aria-invalid={invalid || undefined} className={cn(controlClass, 'min-h-20 w-full py-2', className)} {...props} />;
});

export interface Option {
    value: string | number;
    label: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement> & { options: Option[]; placeholder?: string; invalid?: boolean }>(function Select(
    { className, options, placeholder, invalid, ...props },
    ref,
) {
    return (
        <select ref={ref} aria-invalid={invalid || undefined} className={cn(controlClass, 'h-9', className?.match(/(^|\s)w-/) ? '' : 'w-full', className)} {...props}>
            {placeholder !== undefined && <option value="">{placeholder}</option>}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
});

export function Checkbox({ label, checked, onChange, disabled, hint }: { label: ReactNode; checked: boolean; onChange: (checked: boolean) => void; disabled?: boolean; hint?: ReactNode }) {
    const id = useId();
    return (
        <div className="flex items-start gap-2.5">
            <input
                id={id}
                type="checkbox"
                className="mt-0.5 size-4 rounded border-line-strong accent-[var(--color-accent)]"
                checked={checked}
                disabled={disabled}
                onChange={(event) => onChange(event.target.checked)}
            />
            <label htmlFor={id} className="text-sm leading-5">
                <span className="font-medium">{label}</span>
                {hint && <span className="block text-xs text-muted">{hint}</span>}
            </label>
        </div>
    );
}

/** An on/off switch for a boolean field. */
export function Toggle({ checked, onChange, label, disabled }: { checked: boolean; onChange: (checked: boolean) => void; label?: ReactNode; disabled?: boolean }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            disabled={disabled}
            onClick={() => onChange(!checked)}
            className="inline-flex items-center gap-2.5 text-sm disabled:opacity-50"
        >
            <span className={cn('relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors', checked ? 'bg-accent' : 'bg-line-strong')}>
                <span className={cn('absolute top-0.5 size-4 rounded-full bg-white shadow transition-all', checked ? 'start-[18px]' : 'start-0.5')} />
            </span>
            {label && <span className="font-medium">{label}</span>}
        </button>
    );
}
