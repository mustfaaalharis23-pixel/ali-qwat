import { StrictMode, useEffect, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter, Navigate, Outlet, RouterProvider, useLocation, type RouteObject } from 'react-router';
import { MutationCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiError, setUnauthorizedHandler } from './lib/api';
import { I18nProvider, useI18n } from './lib/i18n';
import { sessionKey, useSession } from './lib/session';
import type { AdminModule } from './lib/modules';
import { AppLayout } from './layout/AppLayout';
import { LoginPage } from './pages/Login';
import { ErrorPage, NotFoundPage } from './pages/NotFound';
import { PageSpinner, Toaster } from './components/ui';

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
const moduleRoutes: RouteObject[] = Object.values(modules).flatMap((module) => module.default.routes);

function RequireUser({ children }: { children: ReactNode }) {
    const { data, isLoading } = useSession();
    const location = useLocation();
    if (isLoading) return <PageSpinner />;
    if (!data?.user) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
    return <>{children}</>;
}

function Root() {
    const { ready } = useI18n();
    const location = useLocation();
    useEffect(() => {
        window.scrollTo(0, 0);
    }, [location.pathname]);
    if (!ready) return <PageSpinner />;
    return (
        <>
            <Outlet />
            <Toaster />
        </>
    );
}

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
