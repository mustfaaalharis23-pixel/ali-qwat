import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { FileText, Paperclip, User } from 'lucide-react';
import { api } from '../../lib/api';
import { cn } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Button, Card, CardBody, CardHeader, Field, Input, PageSpinner, Toggle } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import type { Customer } from './types';

export function CustomerFormPage() {
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', 'customers', id], queryFn: () => api.get<{ data: Customer }>(`/customers/${id}`), enabled: Boolean(id) });
    if (id && isLoading) return <PageSpinner />;
    if (id && (error || !data)) return <QueryError error={error} />;
    return <CustomerForm key={id ?? 'new'} customer={data?.data ?? null} />;
}

/** The customer form (the panel's CustomerForm): its details, invoice by invoice, and its contract file. */
function CustomerForm({ customer }: { customer: Customer | null }) {
    const t = useT();
    const navigate = useNavigate();
    const field = (name: string) => t(`filament/resources/customer.fields.${name}`);
    const form = useForm({
        name: customer?.name ?? '',
        payment_type: customer?.payment_type?.value ?? '',
        phone: customer?.phone ?? '',
        email: customer?.email ?? '',
        is_invoice_by_invoice: customer?.is_invoice_by_invoice ?? false,
    });
    const [contract, setContract] = useState<File | null>(null);
    const [removeContract, setRemoveContract] = useState(false);

    const save = useSave(
        () => {
            const body = new FormData();
            body.append('name', form.values.name);
            body.append('payment_type', form.values.payment_type);
            body.append('phone', form.values.phone);
            body.append('email', form.values.email);
            body.append('is_invoice_by_invoice', form.values.is_invoice_by_invoice ? '1' : '0');
            if (contract) body.append('contract', contract);
            if (removeContract) body.append('remove_contract', '1');
            return api.post<{ data: Customer }>(customer ? `/customers/${customer.id}` : '/customers', body);
        },
        {
            invalidate: ['list', 'record', 'options', 'sales'],
            success: t('admin.actions.saved'),
            onSuccess: (result) => navigate(`/customers/${result.data.id}`),
            onError: form.fail,
        },
    );

    return (
        <>
            <PageHeader
                breadcrumbs={[{ label: t('filament/resources/customer.plural_label'), to: '/customers' }, ...(customer ? [{ label: customer.name, to: `/customers/${customer.id}` }] : [])]}
                title={customer ? `${t('admin.actions.edit')} · ${customer.name}` : `${t('admin.actions.new')} · ${t('filament/resources/customer.label')}`}
            />
            <form
                className="max-w-3xl space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    save.mutate(undefined);
                }}
            >
                <Card>
                    <CardHeader icon={<User className="size-4.5" />} title={t('filament/resources/customer.sections.details')} />
                    <CardBody className="grid gap-4 md:grid-cols-2">
                        <Field label={field('name')} required error={form.errors.name}>
                            {(id) => <Input id={id} autoFocus value={form.values.name} maxLength={255} invalid={Boolean(form.errors.name)} onChange={(event) => form.set('name', event.target.value)} />}
                        </Field>
                        <Field label={field('payment_type')} required error={form.errors.payment_type}>
                            {() => (
                                <div className="inline-flex w-fit rounded-lg bg-subtle p-1">
                                    {(['cash', 'postpaid'] as const).map((type) => (
                                        <button
                                            key={type}
                                            type="button"
                                            onClick={() => form.set('payment_type', type)}
                                            className={cn('rounded-md px-3.5 py-1.5 text-sm font-medium', form.values.payment_type === type ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink')}
                                        >
                                            {t(`filament/resources/customer.payment_types.${type}`)}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </Field>
                        <Field label={field('phone')} error={form.errors.phone}>
                            {(id) => <Input id={id} dir="ltr" value={form.values.phone} maxLength={255} onChange={(event) => form.set('phone', event.target.value)} />}
                        </Field>
                        <Field label={field('email')} error={form.errors.email}>
                            {(id) => <Input id={id} type="email" dir="ltr" value={form.values.email} maxLength={255} invalid={Boolean(form.errors.email)} onChange={(event) => form.set('email', event.target.value)} />}
                        </Field>
                        <div className="md:col-span-2">
                            <Toggle checked={form.values.is_invoice_by_invoice} onChange={(value) => form.set('is_invoice_by_invoice', value)} label={field('is_invoice_by_invoice')} />
                            <p className="mt-1 ps-11 text-xs text-muted">{field('is_invoice_by_invoice_hint')}</p>
                        </div>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader icon={<FileText className="size-4.5" />} title={field('contract')} />
                    <CardBody className="space-y-3">
                        {customer?.contract_url && !removeContract && !contract && (
                            <div className="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm">
                                <a href={customer.contract_url} target="_blank" rel="noreferrer" className="inline-flex min-w-0 items-center gap-2 text-accent hover:underline">
                                    <Paperclip className="size-4 shrink-0" />
                                    <span className="truncate">{customer.contract?.split('/').pop()}</span>
                                </a>
                                <Button size="sm" variant="ghost" className="text-danger" onClick={() => setRemoveContract(true)}>
                                    {t('admin.actions.remove')}
                                </Button>
                            </div>
                        )}
                        <Field error={form.errors.contract} hint={t('admin/sales.customers.contract_hint')}>
                            {(id) => <Input id={id} type="file" accept="application/pdf,image/jpeg,image/png,image/webp" className="py-1.5" onChange={(event) => setContract(event.target.files?.[0] ?? null)} />}
                        </Field>
                    </CardBody>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button onClick={() => navigate(customer ? `/customers/${customer.id}` : '/customers')}>{t('admin.actions.cancel')}</Button>
                    <Button type="submit" variant="primary" loading={save.isPending}>
                        {customer ? t('admin.actions.save_changes') : t('admin.actions.create')}
                    </Button>
                </div>
            </form>
        </>
    );
}
