import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Box } from 'lucide-react';
import { api } from '../../lib/api';
import { number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Button, Empty, Modal, Skeleton } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import { columnLabel, PeriodBar, ReportTable, TotalCards, useReport, useReportParams, type ReportPayload } from './shared';

interface PurchaseRow {
    supplier_id: number;
    supplier: string;
    amount: number;
}

interface SupplierItems {
    heading: string;
    items: { name: string; quantity: number; amount: number }[];
    total: number;
    total_text: string;
}

/** The items bought from one supplier inside the fetched period, with their quantities, amounts, and total. */
function SupplierItemsModal({ supplier, period, onClose }: { supplier: PurchaseRow | null; period: { from: string; to: string }; onClose: () => void }) {
    const t = useT();
    const { data, error } = useQuery({
        queryKey: ['reports', 'purchases', 'supplier-items', supplier?.supplier_id, period],
        queryFn: () => api.get<SupplierItems>(`/reports/purchases/suppliers/${supplier!.supplier_id}/items`, period),
        enabled: supplier !== null,
    });

    return (
        <Modal
            open={supplier !== null}
            onClose={onClose}
            size="lg"
            title={
                data?.heading ??
                t('filament/reports/purchases.items.heading', {
                    supplier: supplier?.supplier,
                })
            }
            footer={<Button onClick={onClose}>{t('filament/reports/purchases.items.close')}</Button>}
        >
            {error ? (
                <QueryError error={error} />
            ) : !data ? (
                <div className="space-y-2">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton key={index} className="h-8 w-full" />
                    ))}
                </div>
            ) : data.items.length === 0 ? (
                <Empty title={t('filament/reports.empty.no_data')} />
            ) : (
                <div className="-mx-1 overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-line text-xs text-muted">
                                <th className="px-3 py-2 text-start font-medium">{t('filament/reports/purchases.items.item')}</th>
                                <th className="px-3 py-2 text-end font-medium">{t('filament/reports/purchases.items.quantity')}</th>
                                <th className="px-3 py-2 text-end font-medium">{t('filament/reports/purchases.items.amount')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.items.map((item, index) => (
                                <tr key={index} className="border-b border-line">
                                    <td className="px-3 py-2">{item.name}</td>
                                    <td className="px-3 py-2 text-end tabular-nums">{number(item.quantity)}</td>
                                    <td className="px-3 py-2 text-end tabular-nums">{number(item.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colSpan={2} className="px-3 py-2.5 font-semibold">
                                    {t('filament/reports/purchases.items.total')}
                                </td>
                                <td className="px-3 py-2.5 text-end font-semibold text-accent tabular-nums">{data.total_text}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </Modal>
    );
}

export function PurchasesReportPage() {
    const t = useT();
    const { params, update } = useReportParams();
    const query = { from: params.get('from'), to: params.get('to') };
    const { data, error, isFetching } = useReport<ReportPayload<PurchaseRow>>('purchases', query);
    const [supplier, setSupplier] = useState<PurchaseRow | null>(null);

    if (error) return <QueryError error={error} />;

    return (
        <>
            <PageHeader title={data?.title ?? t('filament/reports/purchases.title')} description={t('admin/reports.descriptions.purchases')} />
            <PeriodBar from={data?.from} to={data?.to} fetching={isFetching} excelUrl={data?.excel_url} onFetch={(period) => update(period)} />
            <ReportTable
                rows={data?.rows}
                loading={isFetching}
                rowKey={(row) => row.supplier_id}
                columns={[
                    {
                        name: 'supplier',
                        label: columnLabel(data, 'supplier', t('filament/reports/purchases.columns.supplier')),
                        className: 'font-medium',
                    },
                    {
                        name: 'amount',
                        label: columnLabel(data, 'amount', t('filament/reports/purchases.columns.amount')),
                        numeric: true,
                        className: 'font-semibold',
                        render: (row) => number(row.amount),
                    },
                ]}
                actions={(row) => (
                    <Button size="sm" variant="ghost" icon={<Box className="size-4" />} onClick={() => setSupplier(row)}>
                        {t('filament/reports/purchases.items.action')}
                    </Button>
                )}
            />
            {data && <TotalCards totals={data.totals} className="mt-4" />}
            {data && <SupplierItemsModal supplier={supplier} period={{ from: data.from, to: data.to }} onClose={() => setSupplier(null)} />}
        </>
    );
}
