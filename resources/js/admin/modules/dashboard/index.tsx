import { defineModule } from '../../lib/modules';
import { HomePage } from './HomePage';
import { FinancialDashboardPage } from './FinancialDashboardPage';
import { MonitoringDashboardPage } from './MonitoringDashboardPage';

export default defineModule({
    routes: [
        { index: true, element: <HomePage /> },
        { path: 'financial-dashboard', element: <FinancialDashboardPage /> },
        { path: 'monitoring-dashboard', element: <MonitoringDashboardPage /> },
    ],
});
