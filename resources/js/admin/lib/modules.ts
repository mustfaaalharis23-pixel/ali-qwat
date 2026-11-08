import type { RouteObject } from 'react-router';

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
