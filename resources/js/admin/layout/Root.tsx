import { Fragment, useEffect, type ReactNode } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router';
import { useI18n } from '../lib/i18n';
import { useSession } from '../lib/session';
import { PageSpinner, Toaster } from '../components/ui';

/*
 * The application's frame around its routes. It lives outside main.tsx on purpose: the entry file holds no component, so the
 * dev server never has it import itself under a second address, which would start React twice on the same page.
 */

/** Every screen, once the texts of the language are loaded, opened at its top, with the toasts above it. */
export function Root() {
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

/** The screens behind the login: a visitor without a session goes to the login page, and comes back after it. */
export function RequireUser({ children }: { children: ReactNode }) {
    const { data, isLoading } = useSession();
    const location = useLocation();
    if (isLoading) return <PageSpinner />;
    if (!data?.user) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
    return <>{children}</>;
}

/** A create page starts empty on every visit, also when "create another" opens it again from itself. */
export function FreshOnEveryVisit({ children }: { children: ReactNode }) {
    const { key } = useLocation();
    return <Fragment key={key}>{children}</Fragment>;
}
