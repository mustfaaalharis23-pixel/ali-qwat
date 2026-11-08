import {
    Archive, Banknote, BarChart3, Box, Building2, Calculator, ChartLine, Circle, ClipboardList, Coins, Container, DollarSign, Eye, FileChartColumn, FileText,
    History, House, JapaneseYen, Layers, LayoutDashboard, Package, Palette, Printer, Receipt, Scale, ShieldAlert, ShieldCheck, ShoppingBag, ShoppingCart, Store,
    Tag, TriangleAlert, Trophy, Truck, UserRound, Users, Wallet, type LucideIcon,
} from 'lucide-react';

/** The panel's heroicon names (as the navigation sends them) drawn with their nearest Lucide icon. */
const map: Record<string, LucideIcon> = {
    'o-home': House,
    'o-banknotes': Banknote,
    'o-shopping-bag': ShoppingBag,
    'o-shopping-cart': ShoppingCart,
    'o-truck': Truck,
    'o-swatch': Palette,
    'o-eye': Eye,
    'o-tag': Tag,
    'o-cube': Box,
    'o-archive-box': Archive,
    'o-clipboard-document-list': ClipboardList,
    'o-rectangle-stack': Layers,
    'o-shield-exclamation': ShieldAlert,
    'o-shield-check': ShieldCheck,
    'o-printer': Printer,
    'o-document-chart-bar': FileChartColumn,
    'o-chart-bar': BarChart3,
    'o-presentation-chart-line': ChartLine,
    'o-trophy': Trophy,
    'o-scale': Scale,
    'o-user-group': Users,
    'o-user': UserRound,
    'o-document-text': FileText,
    'o-receipt-percent': Receipt,
    'o-calculator': Calculator,
    'o-currency-dollar': DollarSign,
    'o-currency-yen': JapaneseYen,
    'o-building-storefront': Store,
    'o-building-office': Building2,
    'o-exclamation-triangle': TriangleAlert,
    'o-clock': History,
    'o-squares-2x2': LayoutDashboard,
    'o-wallet': Wallet,
    'o-cube-transparent': Container,
    'o-gift': Package,
    'o-circle-stack': Coins,
};

export function NavIcon({ name, className }: { name?: string | null; className?: string }) {
    const Icon = (name && map[name]) || (name?.includes('home') ? House : Circle);
    return <Icon className={className ?? 'size-4.5'} strokeWidth={1.75} />;
}
