import { defineModule, page } from '../../lib/modules';
const SalesReportPage = page(() => import('./SalesReportPage'), 'SalesReportPage');
const PurchasesReportPage = page(() => import('./PurchasesReportPage'), 'PurchasesReportPage');
const ItemSalesReportPage = page(() => import('./ItemSalesReportPage'), 'ItemSalesReportPage');
const GoldenReportPage = page(() => import('./GoldenReportPage'), 'GoldenReportPage');
const ProfitAndLossPage = page(() => import('./ProfitAndLossPage'), 'ProfitAndLossPage');

/** The reports, at the panel's own addresses. */
export default defineModule({
    routes: [
        { path: 'reports/sales', element: <SalesReportPage /> },
        { path: 'reports/purchases', element: <PurchasesReportPage /> },
        { path: 'reports/item-sales', element: <ItemSalesReportPage /> },
        { path: 'reports/golden', element: <GoldenReportPage /> },
        { path: 'reports/profit-and-loss', element: <ProfitAndLossPage /> },
    ],
});
