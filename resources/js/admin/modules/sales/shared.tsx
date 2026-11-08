import { ChevronDown, FileText, Printer, ReceiptText } from 'lucide-react';
import { useT } from '../../lib/i18n';
import { Badge, Button, Menu, toneOf } from '../../components/ui';
import type { EnumBadge, PrintLinks } from './types';

/** A payment type (or any enum) as a badge in its color. */
export function EnumBadgeView({ value }: { value: EnumBadge | null | undefined }) {
    if (!value) return <span className="text-faint">—</span>;
    return <Badge tone={toneOf(value.color)}>{value.label}</Badge>;
}

/** The print button of an order: its invoice as a full page or as the small printer's receipt, each in a new tab. */
export function PrintMenu({ links, size = 'md' }: { links: PrintLinks; size?: 'sm' | 'md' }) {
    const t = useT();
    return (
        <Menu
            items={[
                { label: t('filament/pages/invoice_printing.actions.print_a4'), icon: <FileText className="size-4" />, href: links.a4 },
                { label: t('filament/pages/invoice_printing.actions.print_pos'), icon: <ReceiptText className="size-4" />, href: links.pos },
            ]}
            trigger={(toggle) => (
                <Button size={size} icon={<Printer className="size-4" />} onClick={toggle}>
                    {t('filament/pages/invoice_printing.actions.print')}
                    <ChevronDown className="size-3.5 opacity-60" />
                </Button>
            )}
        />
    );
}

/** The SPA path of a customer's account statement. */
export const statementPath = (customerId: number) => `/customer-payments?tab=statement&customer=${customerId}`;
