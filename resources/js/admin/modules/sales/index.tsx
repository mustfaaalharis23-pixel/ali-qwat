import { defineModule, page } from '../../lib/modules';
const CustomerFormPage = page(() => import('./CustomerFormPage'), 'CustomerFormPage');
const CustomerPage = page(() => import('./CustomerPage'), 'CustomerPage');
const CustomerPaymentsPage = page(() => import('./CustomerPaymentsPage'), 'CustomerPaymentsPage');
const CustomersPage = page(() => import('./CustomersPage'), 'CustomersPage');
const InvoicePrintingPage = page(() => import('./InvoicePrintingPage'), 'InvoicePrintingPage');
const OrderFormPage = page(() => import('./OrderFormPage'), 'OrderFormPage');
const OrderPage = page(() => import('./OrderPage'), 'OrderPage');
const OrdersPage = page(() => import('./OrdersPage'), 'OrdersPage');

/** The sales screens, at the panel's own paths: the orders, the customers, the customer payments, and the invoice printing. */
export default defineModule({
    routes: [
        { path: 'orders', element: <OrdersPage /> },
        { path: 'orders/create', element: <OrderFormPage /> },
        { path: 'orders/:id', element: <OrderPage /> },
        { path: 'orders/:id/edit', element: <OrderFormPage /> },
        { path: 'customers', element: <CustomersPage /> },
        { path: 'customers/create', element: <CustomerFormPage /> },
        { path: 'customers/:id', element: <CustomerPage /> },
        { path: 'customers/:id/edit', element: <CustomerFormPage /> },
        { path: 'customer-payments', element: <CustomerPaymentsPage /> },
        { path: 'invoice-printing', element: <InvoicePrintingPage /> },
    ],
});
