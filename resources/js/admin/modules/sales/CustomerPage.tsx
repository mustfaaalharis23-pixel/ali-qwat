import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Banknote, Check, FileText, Paperclip, Pencil, Printer, RotateCcw, Trash2, User } from 'lucide-react';
import { api } from '../../lib/api';
import { date, money } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, Card, CardBody, CardHeader, DescriptionList, PageSpinner } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { RemoteMetric } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { useRecordActions } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { PayDebtModal } from './PayDebtModal';
import { EnumBadgeView, statementPath } from './shared';
import type { Customer, CustomerPayment } from './types';
import { AuditsSection } from '../system/AuditsPanel';

interface CustomerShow {
    data: Customer;
    metrics: string[];
    can: { view_payments: boolean; delete_payments: boolean };
}

/**
 * A customer's page (the panel's ViewCustomer): its total cards (its account, then its buying), its details and contract, and its
 * payments, which are only deleted here. The header pays its debt, opens its account statement, and edits it.
 */
export function CustomerPage() {
    const t = useT();
    const { id } = useParams();
    const navigate = useNavigate();
    const [paying, setPaying] = useState(false);
    const { data, isLoading, error } = useQuery({ queryKey: ['record', 'customers', id], queryFn: () => api.get<CustomerShow>(`/customers/${id}`) });
    const actions = useRecordActions<Customer>('/customers', { label: (customer) => customer.name, onDeleted: () => navigate('/customers') });
    const paymentActions = useRecordActions<CustomerPayment>('/customer-payments', { label: (payment) => `#${payment.id}` });
    const field = (name: string) => t(`filament/resources/customer.fields.${name}`);
    const paymentField = (name: string) => t(`filament/resources/customer_payment.fields.${name}`);

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;

    const customer = data.data;
    const menu = actions.items(customer);
    const run = (label: string) => menu.find((item) => item.label === label && !item.hidden)?.onSelect?.();

    return (
        <>
            <PageHeader
                breadcrumbs={[{ label: t('filament/resources/customer.plural_label'), to: '/customers' }, { label: customer.name }]}
                title={customer.name}
                badge={customer.deleted_at ? <Badge tone="danger">{t('admin.actions.deleted')}</Badge> : <EnumBadgeView value={customer.payment_type} />}
                actions={
                    <>
                        {customer.can.pay && (
                            <Button variant="success" icon={<Banknote className="size-4" />} onClick={() => setPaying(true)}>
                                {t('filament/resources/customer.actions.pay_debt.label')}
                            </Button>
                        )}
                        <Button icon={<FileText className="size-4" />} onClick={() => navigate(statementPath(customer.id))}>
                            {t('filament/resources/customer.actions.statement')}
                        </Button>
                        {customer.can.update && (
                            <ButtonLink to={`/customers/${customer.id}/edit`} icon={<Pencil className="size-4" />}>
                                {t('admin.actions.edit')}
                            </ButtonLink>
                        )}
                        {customer.can.restore && (
                            <Button icon={<RotateCcw className="size-4" />} onClick={() => actions.restore.mutate(customer)}>
                                {t('admin.actions.restore')}
                            </Button>
                        )}
                        {customer.can.delete && (
                            <Button variant="ghost" className="text-danger" icon={<Trash2 className="size-4" />} onClick={() => run(t('admin.actions.delete'))}>
                                {t('admin.actions.delete')}
                            </Button>
                        )}
                        {customer.can.force_delete && (
                            <Button variant="ghost" className="text-danger" icon={<Trash2 className="size-4" />} onClick={() => run(t('admin.actions.force_delete'))}>
                                {t('admin.actions.force_delete')}
                            </Button>
                        )}
                    </>
                }
            />

            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                {data.metrics.map((key) => (
                    <RemoteMetric key={key} endpoint={`/customers/${customer.id}/metrics/${key}`} />
                ))}
            </div>

            <Card className="mb-5">
                <CardHeader icon={<User className="size-4.5" />} title={t('filament/resources/customer.sections.details')} />
                <CardBody>
                    <DescriptionList
                        columns={3}
                        items={[
                            { label: field('phone'), value: customer.phone ? <a href={`tel:${customer.phone}`} dir="ltr" className="hover:text-accent">{customer.phone}</a> : '—' },
                            { label: field('email'), value: customer.email ?? '—' },
                            { label: field('payment_type'), value: <EnumBadgeView value={customer.payment_type} /> },
                            { label: field('is_invoice_by_invoice'), value: customer.is_invoice_by_invoice ? <Check className="size-4 text-success" /> : '—' },
                            {
                                label: field('contract'),
                                value: customer.contract_url ? (
                                    <a href={customer.contract_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-accent hover:underline">
                                        <Paperclip className="size-3.5" />
                                        {t('admin.actions.view')}
                                    </a>
                                ) : (
                                    '—'
                                ),
                            },
                        ]}
                    />
                </CardBody>
            </Card>

            {data.can.view_payments && (
                <>
                    <h2 className="mb-3 text-[15px] font-semibold">{t('filament/resources/customer_payment.plural_label')}</h2>
                    <DataTable<CustomerPayment>
                        endpoint={`/customers/${customer.id}/payments`}
                        stateKey="payments"
                        trashed
                        defaultSort={{ column: 'date', direction: 'desc' }}
                        columns={[
                            { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (payment) => <span className="text-muted tabular-nums">{payment.id}</span> },
                            {
                                key: 'date',
                                header: paymentField('date'),
                                sortable: true,
                                cell: (payment) => (
                                    <span className="inline-flex items-center gap-2 tabular-nums">
                                        {date(payment.date)}
                                        {payment.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                                    </span>
                                ),
                            },
                            { key: 'amount', header: paymentField('amount'), sortable: true, align: 'end', cell: (payment) => <span className="font-medium tabular-nums">{money(payment.amount)}</span> },
                            {
                                key: 'image',
                                header: paymentField('image'),
                                cell: (payment) =>
                                    payment.image_url ? (
                                        <a href={payment.image_url} target="_blank" rel="noreferrer">
                                            <img src={payment.image_url} alt="" className="size-9 rounded-md object-cover ring-1 ring-line" />
                                        </a>
                                    ) : (
                                        <span className="text-faint">—</span>
                                    ),
                            },
                            { key: 'user', header: paymentField('user_id'), cell: (payment) => payment.user ?? '—' },
                        ]}
                        rowActions={(payment) => [
                            { label: t('filament/resources/customer_payment.actions.print_receipt'), icon: <Printer className="size-4" />, href: payment.receipt_url ?? undefined, hidden: !payment.receipt_url },
                            ...paymentActions.items(payment),
                        ]}
                    />
                </>
            )}

            <AuditsSection subjectType="customer" subjectId={customer.id} />

            {paying && <PayDebtModal customerId={customer.id} onClose={() => setPaying(false)} />}
            {actions.dialogs}
            {paymentActions.dialogs}
        </>
    );
}
