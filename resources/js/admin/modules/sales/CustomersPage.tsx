import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Banknote, Check, Eye, FileText, Pencil, Plus } from 'lucide-react';
import { money } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, ButtonLink } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { useRecordActions } from '../../components/RecordActions';
import { PayDebtModal } from './PayDebtModal';
import { EnumBadgeView, statementPath } from './shared';
import type { Customer } from './types';
import { useSalesAbilities } from './useSalesAbilities';
import { useBulkActions } from './BulkActions';

/** The customers list (the panel's CustomersTable): each customer with its debt, searched by name, phone, or email. */
export function CustomersPage() {
    const t = useT();
    const navigate = useNavigate();
    const [paying, setPaying] = useState<Customer | null>(null);
    const actions = useRecordActions<Customer>('/customers', { label: (customer) => customer.name });
    const field = (name: string) => t(`filament/resources/customer.fields.${name}`);
    const abilities = useSalesAbilities();
    const bulk = useBulkActions('/customers', { delete: abilities?.delete_customers, restore: abilities?.restore_customers, force_delete: abilities?.force_delete_customers });

    return (
        <>
            <PageHeader title={t('filament/resources/customer.plural_label')} />
            <DataTable<Customer>
                endpoint="/customers"
                trashed
                filters={[
                    {
                        key: 'payment_type',
                        label: field('payment_type'),
                        options: [
                            { value: 'cash', label: t('filament/resources/customer.payment_types.cash') },
                            { value: 'postpaid', label: t('filament/resources/customer.payment_types.postpaid') },
                        ],
                    },
                    {
                        key: 'is_invoice_by_invoice',
                        label: field('is_invoice_by_invoice'),
                        options: [
                            { value: '1', label: t('admin.actions.yes') },
                            { value: '0', label: t('admin.actions.no') },
                        ],
                    },
                ]}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <ButtonLink to="/customers/create" variant="primary" size="sm" icon={<Plus className="size-4" />}>
                            {t('admin.actions.new')}
                        </ButtonLink>
                    )
                }
                rowHref={(customer) => `/customers/${customer.id}`}
                selectable={bulk.selectable}
                bulkActions={bulk.buttons}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (customer) => <span className="text-muted tabular-nums">{customer.id}</span> },
                    {
                        key: 'name',
                        header: field('name'),
                        sortable: true,
                        cell: (customer) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                {customer.name}
                                {customer.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'phone', header: field('phone'), sortable: true, cell: (customer) => <span dir="ltr">{customer.phone ?? '—'}</span> },
                    { key: 'email', header: field('email'), cell: (customer) => customer.email ?? '—' },
                    { key: 'payment_type', header: field('payment_type'), cell: (customer) => <EnumBadgeView value={customer.payment_type} /> },
                    {
                        key: 'is_invoice_by_invoice',
                        header: field('is_invoice_by_invoice'),
                        align: 'center',
                        cell: (customer) => (customer.is_invoice_by_invoice ? <Check className="mx-auto size-4 text-success" /> : <span className="text-faint">—</span>),
                    },
                    {
                        key: 'debt',
                        header: t('filament/resources/customer.widgets.totals.debt'),
                        sortable: true,
                        align: 'end',
                        cell: (customer) => <span className={customer.debt > 0 ? 'font-medium text-danger tabular-nums' : 'text-muted tabular-nums'}>{money(customer.debt)}</span>,
                    },
                ]}
                rowActions={(customer) => [
                    { label: t('filament/resources/customer.actions.pay_debt.label'), icon: <Banknote className="size-4" />, onSelect: () => setPaying(customer), hidden: !customer.can.pay },
                    { label: t('admin.actions.view'), icon: <Eye className="size-4" />, onSelect: () => navigate(`/customers/${customer.id}`) },
                    { label: t('filament/resources/customer.actions.statement'), icon: <FileText className="size-4" />, onSelect: () => navigate(statementPath(customer.id)) },
                    { label: t('admin.actions.edit'), icon: <Pencil className="size-4" />, onSelect: () => navigate(`/customers/${customer.id}/edit`), hidden: !customer.can.update },
                    ...actions.items(customer),
                ]}
            />
            {paying && <PayDebtModal customerId={paying.id} onClose={() => setPaying(null)} />}
            {actions.dialogs}
            {bulk.dialog}
        </>
    );
}
