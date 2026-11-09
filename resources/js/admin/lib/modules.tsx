import { lazy, Suspense, type ComponentType } from 'react';
import type { RouteObject } from 'react-router';
import { PageSpinner } from '../components/ui/Spinner';

/**
 * A module of the admin application: the screens of one area (sales, purchases, items, ...).
 * Every resources/js/admin/modules/{name}/index.tsx exports one as its default, and the router picks it up on its own,
 * so a module never edits a shared file to add its screens. Paths are the panel's own (/orders, /reports/golden).
 */
export interface AdminModule {
    routes: RouteObject[];
}

export function defineModule(module: AdminModule): AdminModule {
    return module;
}

/**
 * A screen loaded only when it is opened: page(() => import('./OrdersPage'), 'OrdersPage').
 * Each screen is its own small file in the build, so the first visit downloads the shell and the screen it opens, not all of them.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function page<M extends Record<string, any>>(load: () => Promise<M>, name: keyof M & string) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const Lazy = lazy(() => load().then((module) => ({ default: module[name] as ComponentType<any> })));
    return function Page(props: Record<string, unknown>) {
        return (
            <Suspense fallback={<PageSpinner />}>
                <Lazy {...props} />
            </Suspense>
        );
    };
}
