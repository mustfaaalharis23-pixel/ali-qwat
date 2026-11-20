import { useRef, useState } from 'react';
import { FileSpreadsheet } from 'lucide-react';
import { ExcelTemplateDownload } from '../../components/ExcelTemplateDownload';
import { Badge, Button, Modal, toast } from '../../components/ui';
import { api } from '../../lib/api';
import { cn, money } from '../../lib/format';
import { useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { ITEM_INVALIDATE } from './ItemActions';

interface WorkbookRow {
    row: number;
    name: string | null;
    pieces_per_packet: string | null;
    category: string | null;
    supplier: string | null;
    sale_price: number | null;
    description: string | null;
    errors: Record<string, string> | [];
}

const fields = ['name', 'pieces_per_packet', 'category', 'supplier', 'sale_price', 'description'] as const;
const ready = (row: WorkbookRow) => Object.keys(row.errors).length === 0;

export function ItemExcel() {
    const t = useT();
    const label = (key: string, values?: Record<string, string | number>) => t(`item_excel.${key}`, values);
    const fileInput = useRef<HTMLInputElement>(null);
    const [review, setReview] = useState<{ file: File; rows: WorkbookRow[] } | null>(null);
    const [selected, setSelected] = useState<number[]>([]);

    const preview = useSave(async (file: File) => {
        const body = new FormData();
        body.append('file', file);
        const response = await api.post<{ rows: WorkbookRow[] }>('/items/excel', body);
        return { file, rows: response.rows };
    }, {
        onSuccess: (result) => {
            setReview(result);
            setSelected(result.rows.filter(ready).map((row) => row.row));
        },
    });

    const save = useSave((input: { file: File; rows: number[] }) => {
        const body = new FormData();
        body.append('file', input.file);
        input.rows.forEach((row) => body.append('rows[]', String(row)));
        return api.post<{ count: number }>('/items/excel/import', body);
    }, {
        invalidate: ITEM_INVALIDATE,
        onSuccess: ({ count }) => {
            setReview(null);
            toast.success(label('imported', { count }));
        },
    });

    const readyCount = review?.rows.filter(ready).length ?? 0;
    const issuesCount = (review?.rows.length ?? 0) - readyCount;

    return (
        <>
            <ExcelTemplateDownload endpoint="/items/excel/template" translationKey="item_excel" />
            <input
                ref={fileInput}
                type="file"
                className="hidden"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                onChange={(event) => {
                    const file = event.currentTarget.files?.[0];
                    event.currentTarget.value = '';
                    if (file) preview.mutate(file);
                }}
            />
            <Button icon={<FileSpreadsheet className="size-4" />} loading={preview.isPending} onClick={() => fileInput.current?.click()}>
                {label('upload')}
            </Button>
            <Modal
                open={review !== null}
                onClose={() => setReview(null)}
                size="2xl"
                title={label('review')}
                description={<bdi>{review?.file.name}</bdi>}
                footer={review && (
                    <>
                        <span className="me-auto self-center text-sm text-muted">{label('selected', { count: selected.length })}</span>
                        <Button disabled={save.isPending} onClick={() => setReview(null)}>{t('admin.actions.cancel')}</Button>
                        <Button variant="primary" disabled={selected.length === 0} loading={save.isPending} onClick={() => save.mutate({ file: review.file, rows: selected })}>
                            {label('import')}
                        </Button>
                    </>
                )}
            >
                {review && (
                    <div className="space-y-3">
                        <div className="flex flex-wrap gap-2">
                            <Badge tone="success">{label('ready', { count: readyCount })}</Badge>
                            {issuesCount > 0 && <Badge tone="warning">{label('needs_review', { count: issuesCount })}</Badge>}
                        </div>
                        {issuesCount > 0 && <p className="text-sm text-muted">{label('fix_rows')}</p>}
                        <div className="overflow-x-auto rounded-xl border border-line">
                            <table className="w-full min-w-180 text-start text-sm">
                                <thead className="bg-subtle text-xs text-muted">
                                    <tr>
                                        <th scope="col" className="px-3 py-2 text-start">{label('row')}</th>
                                        {fields.map((field) => <th key={field} scope="col" className="px-3 py-2 text-start">{label(`columns.${field}`)}</th>)}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {review.rows.map((row) => (
                                        <tr key={row.row} className={cn('align-top', !ready(row) && 'bg-warning-soft/30')}>
                                            <td className="px-3 py-2">
                                                <label className="flex items-center gap-2">
                                                    <input
                                                        type="checkbox"
                                                        className="size-4 accent-accent"
                                                        aria-label={label('select_row', { row: row.row })}
                                                        checked={selected.includes(row.row)}
                                                        disabled={!ready(row) || save.isPending}
                                                        onChange={(event) => setSelected(event.target.checked ? [...selected, row.row] : selected.filter((number) => number !== row.row))}
                                                    />
                                                    <span className="tabular-nums">{row.row}</span>
                                                </label>
                                            </td>
                                            {fields.map((field) => {
                                                const error = (row.errors as Record<string, string>)[field];
                                                const value = field === 'sale_price' ? money(row.sale_price) : row[field] ?? '—';
                                                return (
                                                    <td key={field} className="px-3 py-2">
                                                        <bdi className="break-words">{value}</bdi>
                                                        {error && <p className="mt-1 text-xs text-danger">{error}</p>}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
}
