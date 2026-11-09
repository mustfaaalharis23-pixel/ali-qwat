import { defineModule, page } from '../../lib/modules';
const PurchaseInvoicesPage = page(() => import('./PurchaseInvoicesPage'), 'PurchaseInvoicesPage');
const PurchaseInvoicePage = page(() => import('./PurchaseInvoicePage'), 'PurchaseInvoicePage');
const CreatePurchaseInvoicePage = page(() => import('./PurchaseInvoiceFormPage'), 'CreatePurchaseInvoicePage');
const EditPurchaseInvoicePage = page(() => import('./PurchaseInvoiceFormPage'), 'EditPurchaseInvoicePage');
const CreateSupplierPage = page(() => import('./SuppliersPage'), 'CreateSupplierPage');
const EditSupplierPage = page(() => import('./SuppliersPage'), 'EditSupplierPage');
const SupplierPage = page(() => import('./SuppliersPage'), 'SupplierPage');
const SuppliersPage = page(() => import('./SuppliersPage'), 'SuppliersPage');
const ContainerPage = page(() => import('./ContainersPage'), 'ContainerPage');
const ContainersPage = page(() => import('./ContainersPage'), 'ContainersPage');
const CreateContainerPage = page(() => import('./ContainersPage'), 'CreateContainerPage');
const EditContainerPage = page(() => import('./ContainersPage'), 'EditContainerPage');
const InvoiceCategoriesPage = page(() => import('./InvoiceCategoriesPage'), 'InvoiceCategoriesPage');

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
