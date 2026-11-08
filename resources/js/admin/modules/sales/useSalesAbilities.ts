import { useQuery } from '@tanstack/react-query';
import { api } from '../../lib/api';

export interface SalesAbilities {
    see_cost: boolean;
    create_order: boolean;
    create_customer: boolean;
    view_payments: boolean;
    create_payment: boolean;
    print_invoices: boolean;
}

/** What the signed-in user may do on the sales screens, such as seeing what the items cost us. */
export function useSalesAbilities() {
    return useQuery({ queryKey: ['sales-abilities'], queryFn: () => api.get<SalesAbilities>('/sales/abilities'), staleTime: 60_000 }).data;
}
