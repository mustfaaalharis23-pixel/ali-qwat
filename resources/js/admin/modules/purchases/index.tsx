import { defineModule } from '../../lib/modules';
import { PurchaseInvoicesPage } from './PurchaseInvoicesPage';
import { PurchaseInvoicePage } from './PurchaseInvoicePage';
import { CreatePurchaseInvoicePage, EditPurchaseInvoicePage } from './PurchaseInvoiceFormPage';
import { CreateSupplierPage, EditSupplierPage, SupplierPage, SuppliersPage } from './SuppliersPage';
import { ContainerPage, ContainersPage, CreateContainerPage, EditContainerPage } from './ContainersPage';
import { InvoiceCategoriesPage } from './InvoiceCategoriesPage';

/** The purchases: purchase invoices with their payoffs, suppliers, containers, and invoice categories, at the panel's own paths. */
export default defineModule({
    routes: [
        { path: 'purchase-invoices', element: <PurchaseInvoicesPage /> },
        { path: 'purchase-invoices/create', element: <CreatePurchaseInvoicePage /> },
        { path: 'purchase-invoices/:id', element: <PurchaseInvoicePage /> },
        { path: 'purchase-invoices/:id/edit', element: <EditPurchaseInvoicePage /> },
        { path: 'suppliers', element: <SuppliersPage /> },
        { path: 'suppliers/create', element: <CreateSupplierPage /> },
        { path: 'suppliers/:id', element: <SupplierPage /> },
        { path: 'suppliers/:id/edit', element: <EditSupplierPage /> },
        { path: 'containers', element: <ContainersPage /> },
        { path: 'containers/create', element: <CreateContainerPage /> },
        { path: 'containers/:id', element: <ContainerPage /> },
        { path: 'containers/:id/edit', element: <EditContainerPage /> },
        { path: 'invoice-categories', element: <InvoiceCategoriesPage /> },
    ],
});
