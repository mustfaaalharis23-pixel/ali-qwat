import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from './api';

/**
 * The texts of the admin application in the user's language, with the same keys as Laravel's __():
 * t('filament/resources/order.fields.customer'), and :placeholders replaced like Laravel does.
 * Arabic is written right to left; the page direction follows the language.
 */
interface Boot {
    locale: string;
    locales: Record<string, string>;
    appName: string;
    translationsVersion?: string;
}

declare global {
    interface Window {
        __ADMIN__: Boot;
    }
}

export const boot: Boot = window.__ADMIN__ ?? { locale: 'ar', locales: { ar: 'العربية', en: 'English', zh_CN: '简体中文' }, appName: 'AKA' };

type Replace = Record<string, string | number | null | undefined>;

interface I18n {
    locale: string;
    locales: Record<string, string>;
    dir: 'rtl' | 'ltr';
    t: (key: string, replace?: Replace) => string;
    has: (key: string) => boolean;
    setLocale: (locale: string) => Promise<void>;
    ready: boolean;
}

const I18nContext = createContext<I18n | null>(null);

function applyDocumentLanguage(locale: string) {
    document.documentElement.lang = locale.replace('_', '-');
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
}

export function I18nProvider({ children }: { children: ReactNode }) {
    const [locale, setLocaleState] = useState(boot.locale);
    const client = useQueryClient();

    const { data: strings, isSuccess } = useQuery({
        queryKey: ['translations', locale],
        queryFn: () => api.get<Record<string, string>>(`/translations/${locale}`, { v: boot.translationsVersion }),
        staleTime: Infinity,
    });

    useEffect(() => {
        applyDocumentLanguage(locale);
    }, [locale]);

    const t = useCallback(
        (key: string, replace: Replace = {}) => {
            let line = strings?.[key] ?? key.split('.').pop()?.replace(/_/g, ' ') ?? key;
            // Longest names first, so :to never replaces the start of :total.
            for (const [name, value] of Object.entries(replace).sort(([a], [b]) => b.length - a.length)) {
                const text = value === null || value === undefined ? '' : String(value);
                line = line
                    .replaceAll(`:${name.toUpperCase()}`, text.toUpperCase())
                    .replaceAll(`:${name.charAt(0).toUpperCase()}${name.slice(1)}`, text.charAt(0).toUpperCase() + text.slice(1))
                    .replaceAll(`:${name}`, text);
            }
            return line;
        },
        [strings],
    );

    const setLocale = useCallback(
        async (next: string) => {
            await api.post('/locale', { locale: next });
            await client.prefetchQuery({ queryKey: ['translations', next], queryFn: () => api.get(`/translations/${next}`, { v: boot.translationsVersion }), staleTime: Infinity });
            setLocaleState(next);
            // Every label the server writes (navigation, metric cards, validation) comes in the new language.
            client.invalidateQueries({ predicate: (query) => query.queryKey[0] !== 'translations' });
        },
        [client],
    );

    const value = useMemo<I18n>(
        () => ({
            locale,
            locales: boot.locales,
            dir: locale === 'ar' ? 'rtl' : 'ltr',
            t,
            has: (key: string) => Boolean(strings?.[key]),
            setLocale,
            ready: isSuccess,
        }),
        [locale, t, setLocale, isSuccess, strings],
    );

    return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18n {
    const context = useContext(I18nContext);
    if (!context) throw new Error('useI18n outside I18nProvider');
    return context;
}

export function useT() {
    return useI18n().t;
}
