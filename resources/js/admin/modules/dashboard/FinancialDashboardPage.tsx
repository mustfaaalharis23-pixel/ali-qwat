import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeftRight } from 'lucide-react';
import { api } from '../../lib/api';
import { money } from '../../lib/format';
import { useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Button, ConfirmDialog, DescriptionList, Select, Skeleton, toast } from '../../components/ui';
import { InfoButton, RemoteMetric } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';

interface Cashiers {
    label: string;
    explanation: string;
    example: string;
    description: string | null;
    can_transfer: boolean;
    cashiers: { id: number; name: string; money: number; money_text: string }[];
}

function CashierTransferCard() {
    const t = useT();
    const [cashierId, setCashierId] = useState('');
    const [confirming, setConfirming] = useState(false);
    const { data } = useQuery({ queryKey: ['dashboard', 'cashiers'], queryFn: () => api.get<Cashiers>('/dashboard/cashiers') });
    const transfer = useSave((body: { cashier_id: number }) => api.post<{ message: string }>('/dashboard/cashiers/transfer', body), {
        invalidate: ['dashboard', 'metric'],
        onSuccess: (result) => {
            setConfirming(false);
            setCashierId('');
            toast.success(result.message);
        },
    });
    const cashier = data?.cashiers.find((item) => String(item.id) === cashierId);

    return (
        <div className="card flex flex-col gap-3 p-4.5 md:col-span-1 xl:col-span-2">
            <div className="flex items-start justify-between gap-2">
                <p className="text-[13px] font-medium text-muted">{data?.label ?? <Skeleton className="h-4 w-36" />}</p>
                {data && <InfoButton title={data.label} explanation={data.explanation} example={data.example} />}
            </div>
            <div className="flex flex-wrap gap-2">
                <Select
                    value={cashierId}
                    onChange={(event) => setCashierId(event.target.value)}
                    options={(data?.cashiers ?? []).map((item) => ({ value: item.id, label: `${item.name} — ${item.money_text}` }))}
                    placeholder={t('metrics.cashier_money_transfer.cashier')}
                    className="min-w-0 flex-1"
                />
                {data?.can_transfer && (
                    <Button variant="primary" icon={<ArrowLeftRight className="size-4" />} disabled={!cashier || cashier.money <= 0} onClick={() => setConfirming(true)}>
                        {t('metrics.cashier_money_transfer.transfer')}
                    </Button>
                )}
            </div>
            <ConfirmDialog
                open={confirming}
                onClose={() => setConfirming(false)}
                onConfirm={() => cashier && transfer.mutate({ cashier_id: cashier.id })}
                loading={transfer.isPending}
                tone="primary"
                title={t('metrics.cashier_money_transfer.confirm_heading')}
                description={t('metrics.cashier_money_transfer.confirm_description')}
                confirmLabel={t('metrics.cashier_money_transfer.transfer')}
            >
                <DescriptionList
                    columns={1}
                    items={[
                        { label: t('metrics.cashier_money_transfer.cashier'), value: cashier?.name },
                        { label: t('metrics.cashier_money_transfer.description'), value: data?.description },
                        { label: t('metrics.cashier_money_transfer.amount'), value: money(cashier?.money) },
                    ]}
                />
            </ConfirmDialog>
        </div>
    );
}

export function FinancialDashboardPage() {
    const t = useT();
    const { data, error } = useQuery({ queryKey: ['dashboard', 'financial'], queryFn: () => api.get<{ title: string; cards: string[]; can_transfer_cashier_money: boolean }>('/dashboard/financial') });

    if (error) return <QueryError error={error} />;

    return (
        <>
            <PageHeader title={data?.title ?? t('filament/pages/financial_dashboard.title')} />
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                {data && <RemoteMetric endpoint="/dashboard/metrics/accountant_balance" className="md:col-span-1 xl:col-span-2" />}
                {data?.can_transfer_cashier_money && <CashierTransferCard />}
                {data?.cards
                    .filter((key) => key !== 'accountant_balance')
                    .map((key) => (
                        <RemoteMetric key={key} endpoint={`/dashboard/metrics/${key}`} />
                    ))}
                {!data && Array.from({ length: 8 }).map((_, index) => <Skeleton key={index} className="h-28 rounded-[var(--radius-card)]" />)}
            </div>
        </>
    );
}
