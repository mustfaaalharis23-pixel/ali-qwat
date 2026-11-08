import { number } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Badge, Field, Tabs } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';
import { columnLabel, PeriodBar, ReportTable, TotalCards, useReport, useReportParams, type ReportPayload } from './shared';

interface ItemSalesRow {
    rank: number;
    item: string;
    quantity: number;
    amount: number;
}

export function ItemSalesReportPage() {
    const t = useT();
    const { params, update } = useReportParams();
    const sort = params.get('sort') === 'amount' ? 'amount' : 'quantity';
    const query = { from: params.get('from'), to: params.get('to'), sort };
    const { data, error, isFetching } = useReport<ReportPayload<ItemSalesRow, { sort: string }>>('item-sales', query);

    if (error) return <QueryError error={error} />;

    const sorted = (column: 'quantity' | 'amount') => (sort === column ? 'font-semibold text-success' : '');

    return (
        <>
            <PageHeader title={data?.title ?? t('filament/reports/item_sales.title')} description={t('admin/reports.descriptions.item_sales')} />
            <PeriodBar from={data?.from} to={data?.to} fetching={isFetching} excelUrl={data?.excel_url} onFetch={(period) => update(period)}>
                <Field label={t('filament/reports/item_sales.sort.label')}>
                    {() => (
                        <Tabs
                            value={sort}
                            onChange={(value) => update({ sort: value })}
                            tabs={[
                                {
                                    value: 'quantity',
                                    label: t('filament/reports/item_sales.sort.quantity'),
                                },
                                {
                                    value: 'amount',
                                    label: t('filament/reports/item_sales.sort.amount'),
                                },
                            ]}
                        />
                    )}
                </Field>
            </PeriodBar>
            <ReportTable
                rows={data?.rows}
                loading={isFetching}
                rowKey={(row) => `${row.rank}-${row.item}`}
                columns={[
                    {
                        name: 'rank',
                        label: '#',
                        render: (row) => <Badge tone={row.rank <= 3 ? 'accent' : 'gray'}>{row.rank}</Badge>,
                    },
                    {
                        name: 'item',
                        label: columnLabel(data, 'item', t('filament/reports/item_sales.columns.item')),
                        className: 'font-medium',
                    },
                    {
                        name: 'quantity',
                        label: columnLabel(data, 'quantity', t('filament/reports/item_sales.columns.quantity')),
                        numeric: true,
                        className: sorted('quantity'),
                        render: (row) => number(row.quantity),
                    },
                    {
                        name: 'amount',
                        label: columnLabel(data, 'amount', t('filament/reports/item_sales.columns.amount')),
                        numeric: true,
                        className: sorted('amount'),
                        render: (row) => number(row.amount),
                    },
                ]}
            />
            {data && <TotalCards totals={data.totals} className="mt-4" />}
        </>
    );
}
