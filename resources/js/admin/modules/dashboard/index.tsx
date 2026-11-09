import { defineModule, page } from '../../lib/modules';
const HomePage = page(() => import('./HomePage'), 'HomePage');
const FinancialDashboardPage = page(() => import('./FinancialDashboardPage'), 'FinancialDashboardPage');
const MonitoringDashboardPage = page(() => import('./MonitoringDashboardPage'), 'MonitoringDashboardPage');

export default defineModule({
    routes: [
        { index: true, element: <HomePage /> },
        { path: 'financial-dashboard', element: <FinancialDashboardPage /> },
        { path: 'monitoring-dashboard', element: <MonitoringDashboardPage /> },
    ],
});
