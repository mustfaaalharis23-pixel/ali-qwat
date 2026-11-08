import { defineModule } from '../../lib/modules';
import { CustomerFormPage } from './CustomerFormPage';
import { CustomerPage } from './CustomerPage';
import { CustomerPaymentsPage } from './CustomerPaymentsPage';
import { CustomersPage } from './CustomersPage';
import { InvoicePrintingPage } from './InvoicePrintingPage';
import { OrderFormPage } from './OrderFormPage';
import { OrderPage } from './OrderPage';
import { OrdersPage } from './OrdersPage';

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
