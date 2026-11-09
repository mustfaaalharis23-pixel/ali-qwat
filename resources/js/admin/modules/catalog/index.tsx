import { defineModule, page } from '../../lib/modules';
const BatchesPage = page(() => import('./BatchesPage'), 'BatchesPage');
const ColorsPage = page(() => import('./ColorsPage'), 'ColorsPage');
const ItemCreatePage = page(() => import('./ItemFormPage'), 'ItemCreatePage');
const ItemEditPage = page(() => import('./ItemFormPage'), 'ItemEditPage');
const ItemPage = page(() => import('./ItemPage'), 'ItemPage');
const ItemsPage = page(() => import('./ItemsPage'), 'ItemsPage');
const LensPage = page(() => import('./LensPage'), 'LensPage');
const LensPowersPage = page(() => import('./LensPowersPage'), 'LensPowersPage');

export default defineModule({
    routes: [
        { path: 'colors', element: <ColorsPage /> },
        { path: 'items', element: <ItemsPage /> },
        { path: 'items/create', element: <ItemCreatePage /> },
        { path: 'items/:id', element: <ItemPage /> },
        { path: 'items/:id/edit', element: <ItemEditPage /> },
        { path: 'batches', element: <BatchesPage /> },
        { path: 'lens-powers', element: <LensPowersPage /> },
        { path: 'lenses/:key', element: <LensPage /> },
    ],
});
