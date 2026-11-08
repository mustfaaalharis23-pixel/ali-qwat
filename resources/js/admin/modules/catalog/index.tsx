import { defineModule } from '../../lib/modules';
import { BatchesPage } from './BatchesPage';
import { ColorsPage } from './ColorsPage';
import { ItemCreatePage, ItemEditPage } from './ItemFormPage';
import { ItemPage } from './ItemPage';
import { ItemsPage } from './ItemsPage';
import { LensPage } from './LensPage';
import { LensPowersPage } from './LensPowersPage';

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
