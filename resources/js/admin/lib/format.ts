/**
 * Numbers, money, and dates as the application writes them: always in English digits, whatever the language,
 * with thousands separators and at most two decimals. Money is in Iraqi dinar unless another currency is given.
 */
const numberFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

export function number(value: number | string | null | undefined, maxDecimals = 2): string {
    if (value === null || value === undefined || value === '') return '—';
    const parsed = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(parsed)) return String(value);
    return maxDecimals === 2 ? numberFormat.format(parsed) : new Intl.NumberFormat('en-US', { maximumFractionDigits: maxDecimals }).format(parsed);
}

/** The currency as the reader writes it: Iraqi dinar is د.ع in Arabic and IQD otherwise; dollars and yuan keep their signs. */
function currencyLabel(currency: string): string {
    if (currency === 'IQD') return document.documentElement.lang === 'ar' ? 'د.ع' : 'IQD';
    return currency === 'USD' ? '$' : currency === 'CNY' ? '¥' : currency;
}

export function money(value: number | string | null | undefined, currency: 'IQD' | 'USD' | 'CNY' | string = 'IQD'): string {
    if (value === null || value === undefined || value === '') return '—';
    const label = currencyLabel(currency);
    return currency === 'USD' || currency === 'CNY' ? `${label}${number(value)}` : `${number(value)} ${label}`;
}

export function date(value: string | null | undefined): string {
    if (!value) return '—';
    return value.slice(0, 10);
}

export function dateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())} ${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
}

export function cn(...classes: (string | false | null | undefined)[]): string {
    return classes.filter(Boolean).join(' ');
}
