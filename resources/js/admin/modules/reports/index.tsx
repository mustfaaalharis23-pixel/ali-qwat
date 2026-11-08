import { defineModule } from '../../lib/modules';
import { SalesReportPage } from './SalesReportPage';
import { PurchasesReportPage } from './PurchasesReportPage';
import { ItemSalesReportPage } from './ItemSalesReportPage';
import { GoldenReportPage } from './GoldenReportPage';
import { ProfitAndLossPage } from './ProfitAndLossPage';

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
