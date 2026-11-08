import type { Abilities } from '../../components/RecordActions';

/** An enum as the server sends it for a badge. */
export interface EnumBadge {
    value: string;
    label: string;
    color: string | null;
}

export interface OrderRow {
    id: number;
    customer: { id: number; name: string } | null;
    date: string | null;
    payment_type: EnumBadge | null;
    items_count?: number;
    amount: number;
    total_amount: number;
    paid_amount: number;
    down_payment: number | null;
    weekly_payment: number | null;
    remaining: number;
    total_cost: number | null;
    profit: number | null;
    profit_margin_percentage: number | null;
    cashier: string | null;
    user: string | null;
    deleted_at: string | null;
    print?: PrintLinks | null;
    can: Abilities;
}

export interface PrintLinks {
    a4: string;
    pos: string;
}

export interface OrderLine {
    id: number;
    item: { id: number; name: string } | null;
    power: string | null;
    colors: { name: string; pieces: number }[];
    quantity: number;
    packets: number;
    pieces: number;
    price: number;
    total_price: number;
    cost_price: number | null;
    profit: number | null;
}

export interface OrderPaymentLine {
    id: number;
    date: string;
    amount: number;
    is_down_payment: boolean;
    user: string | null;
    receipt_url: string;
}

export interface OrderDetails extends OrderRow {
    notes: string | null;
    discount: number;
    lines: OrderLine[];
    payments: OrderPaymentLine[] | null;
    weekly: { amount: number; is_late: boolean; late_text: string; is_due_today: boolean; next_text: string; left_text: string } | null;
    print: PrintLinks | null;
    can: Abilities & { pay: boolean; see_cost: boolean };
}

export interface CartLineInput {
    item_id: number;
    lens_power_id: number | null;
    colors: { color_id: number; pieces: number }[];
    packets: number;
}

export interface ItemCard {
    id: number;
    name: string;
    image: string | null;
    price: string;
    piecesPerPacket: number;
    isOutOfStock: boolean;
    powers: { id: number | null; value: string; dozens: string; isSelected: boolean }[];
    showsTypes: boolean;
    types: { key: string; colors: { name: string; pieces: string }[]; packets: number; dozens: string; isSelected: boolean }[];
    dozensLeft: string;
    packetsLeft: number;
    lineKey: string | null;
    inCart: number;
    inCartBreakdown: string | null;
    line: Omit<CartLineInput, 'packets'> | null;
    powerKey: string;
}

export interface CartSummaryLine {
    key: string;
    name: string;
    image: string | null;
    power: string | number | null;
    colors: { name: string; pieces: string }[];
    packets: number;
    breakdown: string;
    total: number;
    totalText: string;
    item_id: number;
    lens_power_id: number | null;
    colorIds: { color_id: number; pieces: number }[];
    packetsLeft: number;
    dozensLeft: string;
}

export interface CartSummary {
    lines: CartSummaryLine[];
    subtotal: number;
    subtotalText: string;
    discount: number;
    discountText: string;
    discountPercent: number | null;
    total: number;
    totalText: string;
}

export interface Customer {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    payment_type: EnumBadge | null;
    is_invoice_by_invoice: boolean;
    contract: string | null;
    contract_url: string | null;
    debt: number;
    deleted_at: string | null;
    can: Abilities & { pay: boolean };
}

export interface CustomerPayment {
    id: number;
    customer?: { id: number; name: string } | null;
    date: string | null;
    amount: number;
    is_down_payment: boolean;
    image_url: string | null;
    notes: string | null;
    user?: string | null;
    invoice?: { id: number; name: string | null } | null;
    allocations?: { order_id: number; order_date: string | null; order_total: number | null; amount: number }[];
    receipt_url: string | null;
    deleted_at: string | null;
    can: Abilities;
}
