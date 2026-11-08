import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from './api';

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

export function useLogin() {
    const client = useQueryClient();
    return useMutation({
        mutationFn: (credentials: { email: string; password: string; remember: boolean }) => api.post<Session>('/login', credentials),
        onSuccess: (session) => {
            client.clear();
            client.setQueryData(sessionKey, session);
        },
    });
}

export function useLogout() {
    const client = useQueryClient();
    return useMutation({
        mutationFn: () => api.post<Session>('/logout'),
        onSuccess: (session) => {
            client.clear();
            client.setQueryData(sessionKey, session);
        },
    });
}
