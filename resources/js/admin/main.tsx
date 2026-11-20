import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter, RouterProvider, type RouteObject } from 'react-router';
import { MutationCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiError, setUnauthorizedHandler } from './lib/api';
import { I18nProvider } from './lib/i18n';
import { sessionKey } from './lib/session';
import type { AdminModule } from './lib/modules';
import { AppLayout } from './layout/AppLayout';
import { FreshOnEveryVisit, RequireUser, Root } from './layout/Root';
import { LoginPage } from './pages/Login';
import { ErrorPage, NotFoundPage } from './pages/NotFound';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 15_000,
            refetchOnWindowFocus: false,
            retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 1,
        },
    },
    mutationCache: new MutationCache(),
});

setUnauthorizedHandler(() => queryClient.setQueryData(sessionKey, (session: object | undefined) => (session ? { ...session, user: null } : session)));

/** Every module's screens, found on their own: resources/js/admin/modules/{name}/index.tsx. */
const modules = import.meta.glob<{ default: AdminModule }>('./modules/*/index.tsx', { eager: true });
const moduleRoutes: RouteObject[] = Object.values(modules)
    .flatMap((module) => module.default.routes)
    .map((route) => (route.path?.endsWith('/create') ? { ...route, element: <FreshOnEveryVisit>{route.element}</FreshOnEveryVisit> } : route));

const router = createBrowserRouter(
    [
        {
            element: <Root />,
            errorElement: <ErrorPage />,
            children: [
                { path: '/login', element: <LoginPage /> },
                {
                    element: (
                        <RequireUser>
                            <AppLayout />
                        </RequireUser>
                    ),
                    errorElement: <ErrorPage />,
                    children: [...moduleRoutes, { path: '*', element: <NotFoundPage /> }],
                },
            ],
        },
    ],
    { basename: '/app' },
);

createRoot(document.getElementById('root')!).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <I18nProvider>
                <RouterProvider router={router} />
            </I18nProvider>
        </QueryClientProvider>
    </StrictMode>,
);
