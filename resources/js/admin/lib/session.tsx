import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from './api';
import { useI18n } from './i18n';

export interface SessionUser {
    id: number;
    name: string;
    email: string;
    is_super: boolean;
    is_cashier: boolean;
}

export interface Session {
    user: SessionUser | null;
    locale: string;
    locales: Record<string, string>;
}

export const sessionKey = ['session'];

export function useSession() {
    return useQuery({ queryKey: sessionKey, queryFn: () => api.get<Session>('/session'), staleTime: 60_000 });
}

/**
 * Signing in switches to the language saved on the account; signing out brings the login page back to Arabic.
 * The server answers with that language, so the application follows it.
 */
export function useLogin() {
    const client = useQueryClient();
    const { applyLocale } = useI18n();
    return useMutation({
        mutationFn: (credentials: { email: string; password: string; remember: boolean }) => api.post<Session>('/login', credentials),
        onSuccess: async (session) => {
            client.clear();
            client.setQueryData(sessionKey, session);
            await applyLocale(session.locale);
        },
    });
}

export function useLogout() {
    const client = useQueryClient();
    const { applyLocale } = useI18n();
    return useMutation({
        mutationFn: () => api.post<Session>('/logout'),
        onSuccess: async (session) => {
            client.clear();
            client.setQueryData(sessionKey, session);
            await applyLocale(session.locale);
        },
    });
}
