/**
 * The admin API client: JSON over the web session. Writes carry the XSRF token from Laravel's cookie,
 * and a failed answer becomes an ApiError with its status, its message, and the field errors of a 422.
 */
export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
        public errors: Record<string, string[]> = {},
        public body: unknown = null,
    ) {
        super(message);
    }

    /** The first error of a field, or of any field when none is given. */
    first(field?: string): string | undefined {
        if (field) return this.errors[field]?.[0];
        return Object.values(this.errors)[0]?.[0] ?? this.message;
    }
}

export type Query = Record<string, string | number | boolean | null | undefined | (string | number)[] | Record<string, unknown>>;

const BASE = '/admin-api';

function readCookie(name: string): string | null {
    const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`));
    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : null;
}

/** Flattens a query object into a query string, nested objects as filter[key]=value and arrays as key[]=value. */
export function toQueryString(query: Query = {}): string {
    const params = new URLSearchParams();
    const add = (key: string, value: unknown) => {
        if (value === null || value === undefined || value === '') return;
        if (Array.isArray(value)) value.forEach((v) => add(`${key}[]`, v));
        else if (typeof value === 'object') Object.entries(value as Record<string, unknown>).forEach(([k, v]) => add(`${key}[${k}]`, v));
        else if (typeof value === 'boolean') params.append(key, value ? '1' : '0');
        else params.append(key, String(value));
    };
    Object.entries(query).forEach(([key, value]) => add(key, value));
    const string = params.toString();
    return string ? `?${string}` : '';
}

let onUnauthorized: (() => void) | null = null;

/** Called when the session ran out, so the application can show the sign-in page. */
export function setUnauthorizedHandler(handler: () => void) {
    onUnauthorized = handler;
}

async function request<T>(method: string, path: string, body?: unknown, query?: Query): Promise<T> {
    const isForm = body instanceof FormData;
    const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const token = readCookie('XSRF-TOKEN');
    if (token) headers['X-XSRF-TOKEN'] = token;
    if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';

    const response = await fetch(`${BASE}${path}${toQueryString(query)}`, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
    });

    if (response.status === 204) return undefined as T;

    const type = response.headers.get('Content-Type') ?? '';
    const data = type.includes('application/json') ? await response.json() : await response.text();

    if (!response.ok) {
        if (response.status === 401 && onUnauthorized) onUnauthorized();
        const message = typeof data === 'object' && data && 'message' in data ? String((data as { message: unknown }).message) : response.statusText;
        const errors = typeof data === 'object' && data && 'errors' in data ? ((data as { errors: Record<string, string[]> }).errors ?? {}) : {};
        throw new ApiError(response.status, message, errors, data);
    }

    return data as T;
}

export const api = {
    get: <T>(path: string, query?: Query) => request<T>('GET', path, undefined, query),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, body ?? {}),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body ?? {}),
    patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body ?? {}),
    delete: <T>(path: string, body?: unknown) => request<T>('DELETE', path, body),
};

/** A Laravel paginated resource collection. */
export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    links?: unknown;
}
