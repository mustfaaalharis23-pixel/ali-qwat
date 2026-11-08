import { useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Archive, Banknote, Box, ImageUp, RotateCcw, Trash2 } from 'lucide-react';
import { api } from '../../lib/api';
import { number } from '../../lib/format';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, Card, CardBody, CardHeader, Field, Input, PageSpinner, Select, Textarea, Toggle } from '../../components/ui';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import { useRecordActions } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { ITEM_INVALIDATE } from './ItemActions';
import { ItemThumb, type ItemRow } from './ItemsPage';

const PACKET_SIZES = [20, 24];

interface ItemValues extends Record<string, unknown> {
    name: string;
    supplier_id: number | null;
    pieces_per_packet: string;
    description: string;
    is_active: boolean;
    sale_price: string;
}

/**
 * The item form: its details and its image, its sale price, and on an item being edited its stock, read only.
 * The cost price and the profit margin are only shown, and only to a user who may see what the items cost.
 */
function ItemForm({ item, canSeeTheCost }: { item: ItemRow | null; canSeeTheCost: boolean }) {
    const t = useT();
    const navigate = useNavigate();
    const fileInput = useRef<HTMLInputElement>(null);
    const [image, setImage] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(item?.image_url ?? null);
    const form = useForm<ItemValues>({
        name: item?.name ?? '',
        supplier_id: item?.supplier_id ?? null,
        pieces_per_packet: item?.pieces_per_packet ? String(item.pieces_per_packet) : '',
        description: item?.description ?? '',
        is_active: item?.is_active ?? true,
        sale_price: item ? String(item.sale_price ?? '') : '',
    });
    const actions = useRecordActions<ItemRow>('/items', { label: (record) => record.name, onDeleted: () => navigate('/items') });

    const save = useSave(
        (values: ItemValues) => {
            const body = new FormData();
            body.append('name', values.name);
            if (values.supplier_id) body.append('supplier_id', String(values.supplier_id));
            body.append('pieces_per_packet', values.pieces_per_packet);
            body.append('description', values.description);
            body.append('is_active', values.is_active ? '1' : '0');
            body.append('sale_price', values.sale_price);
            if (image) body.append('image', image);
            if (item) body.append('_method', 'PUT');
            return api.post<{ data: ItemRow }>(item ? `/items/${item.id}` : '/items', body);
        },
        {
            invalidate: ITEM_INVALIDATE,
            success: t('admin.actions.saved'),
            onSuccess: (result) => navigate(`/items/${result.data.id}`),
            onError: form.fail,
        },
    );

    const submit = () => save.mutate(form.values);
    const title = item ? `${t('admin.actions.edit')}: ${item.name}` : `${t('admin.actions.create')} ${t('filament/resources/item.label')}`;
    const crumbs = [{ label: t('filament/resources/item.plural_label'), to: '/items' }, ...(item ? [{ label: item.name, to: `/items/${item.id}` }] : []), { label: item ? t('admin.actions.edit') : t('admin.actions.create') }];

    return (
        <>
            <PageHeader
                title={title}
                breadcrumbs={crumbs}
                badge={item?.deleted_at ? <Badge tone="danger">{t('admin.actions.deleted')}</Badge> : undefined}
                actions={
                    item && (
                        <>
                            {item.can.restore && (
                                <Button icon={<RotateCcw className="size-4" />} onClick={() => actions.restore.mutate(item)}>
                                    {t('admin.actions.restore')}
                                </Button>
                            )}
                            {actions.items(item).filter((action) => !action.hidden && action.tone === 'danger').map((action, index) => (
                                <Button key={index} variant="danger" icon={<Trash2 className="size-4" />} onClick={action.onSelect}>
                                    {action.label}
                                </Button>
                            ))}
                        </>
                    )
                }
            />
            <form className="grid gap-6" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <Card>
                    <CardHeader icon={<Box className="size-4.5" />} title={t('filament/resources/item.sections.details')} />
                    <CardBody className="grid gap-5 md:grid-cols-2">
                        <Field label={t('filament/resources/item.fields.name')} error={form.errors.name} required>
                            {(id) => <Input id={id} autoFocus={!item} maxLength={255} value={form.values.name} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                        </Field>
                        <Field label={t('filament/resources/item.fields.supplier_id')} error={form.errors.supplier_id}>
                            {(id) => (
                                <SearchSelect
                                    id={id}
                                    endpoint="/options/suppliers"
                                    value={form.values.supplier_id}
                                    initialLabel={item?.supplier?.name}
                                    onChange={(value) => form.set('supplier_id', value === null ? null : Number(value))}
                                    invalid={Boolean(form.errors.supplier_id)}
                                />
                            )}
                        </Field>
                        <Field label={t('filament/resources/item.fields.image')} hint={t('admin/catalog.image_hint')} error={form.errors.image} required>
                            {(id) => (
                                <div className="flex items-center gap-3">
                                    <ItemThumb url={preview} className="size-16" />
                                    <input
                                        id={id}
                                        ref={fileInput}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="hidden"
                                        onChange={(event) => {
                                            const file = event.target.files?.[0] ?? null;
                                            setImage(file);
                                            if (file) setPreview(URL.createObjectURL(file));
                                            form.setErrors((current) => ({ ...current, image: '' }));
                                        }}
                                    />
                                    <Button type="button" icon={<ImageUp className="size-4" />} onClick={() => fileInput.current?.click()}>
                                        {preview ? t('admin/catalog.change_image') : t('admin/catalog.choose_image')}
                                    </Button>
                                </div>
                            )}
                        </Field>
                        <Field label={t('filament/resources/item.fields.pieces_per_packet')} hint={t('filament/resources/item.hints.pieces_per_packet')} error={form.errors.pieces_per_packet} required>
                            {(id) => (
                                <Select
                                    id={id}
                                    value={form.values.pieces_per_packet}
                                    onChange={(event) => form.set('pieces_per_packet', event.target.value)}
                                    placeholder={t('admin.form.choose')}
                                    options={PACKET_SIZES.map((size) => ({ value: String(size), label: t('filament/resources/item.packet_size', { pieces: size }) }))}
                                    invalid={Boolean(form.errors.pieces_per_packet)}
                                />
                            )}
                        </Field>
                        <Field label={t('filament/resources/item.fields.description')} error={form.errors.description}>
                            {(id) => <Textarea id={id} rows={4} value={form.values.description} onChange={(event) => form.set('description', event.target.value)} />}
                        </Field>
                        <div className="md:col-span-2">
                            <Toggle checked={form.values.is_active} onChange={(checked) => form.set('is_active', checked)} label={t('filament/resources/item.fields.is_active')} />
                        </div>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader icon={<Banknote className="size-4.5" />} title={t('filament/resources/item.sections.prices')} />
                    <CardBody className="grid gap-5 md:grid-cols-3">
                        <Field label={t('filament/resources/item.fields.sale_price')} error={form.errors.sale_price}>
                            {(id) => <Input id={id} inputMode="decimal" dir="ltr" value={form.values.sale_price} onChange={(event) => form.set('sale_price', event.target.value)} invalid={Boolean(form.errors.sale_price)} />}
                        </Field>
                        {canSeeTheCost && (
                            <Field label={t('filament/resources/item.fields.cost_price')} hint={t('filament/resources/item.hints.cost_price')}>
                                {(id) => <Input id={id} disabled dir="ltr" value={number(item?.cost_price ?? 0)} />}
                            </Field>
                        )}
                        {canSeeTheCost && item && (
                            <Field label={t('filament/resources/item.fields.profit_margin')}>
                                {(id) => <Input id={id} disabled dir="ltr" value={number(item.profit_margin ?? 0)} />}
                            </Field>
                        )}
                    </CardBody>
                </Card>

                {item && (
                    <Card>
                        <CardHeader icon={<Archive className="size-4.5" />} title={t('filament/resources/item.sections.stock')} />
                        <CardBody className="grid gap-5 md:grid-cols-2">
                            <Field label={t('filament/resources/item.fields.available_quantity')}>{(id) => <Input id={id} disabled dir="ltr" value={number(item.available_quantity)} />}</Field>
                            <Field label={t('filament/resources/item.fields.incoming_quantity')}>{(id) => <Input id={id} disabled dir="ltr" value={number(item.incoming_quantity)} />}</Field>
                        </CardBody>
                    </Card>
                )}

                <div className="flex flex-wrap justify-end gap-2">
                    <Button type="button" onClick={() => navigate(item ? `/items/${item.id}` : '/items')}>
                        {t('admin.actions.cancel')}
                    </Button>
                    <Button type="submit" variant="primary" loading={save.isPending}>
                        {item ? t('admin.actions.save_changes') : t('admin.actions.create')}
                    </Button>
                </div>
            </form>
            {actions.dialogs}
        </>
    );
}

/** The create page, at /items/create. Whether the cost shows comes from the list's abilities. */
export function ItemCreatePage() {
    const { data } = useQuery({ queryKey: ['metric', 'items-list'], queryFn: () => api.get<{ can: { view_cost_price?: boolean } }>('/items/metrics') });
    return <ItemForm item={null} canSeeTheCost={Boolean(data?.can.view_cost_price)} />;
}

/** The edit page, at /items/:id/edit. */
export function ItemEditPage() {
    const { id } = useParams();
    const { data, error } = useQuery({ queryKey: ['record', 'items', id], queryFn: () => api.get<{ data: ItemRow; can: { view_cost_price: boolean } }>(`/items/${id}`) });
    if (error) return <QueryError error={error} />;
    if (!data) return <PageSpinner />;
    return <ItemForm key={data.data.id} item={data.data} canSeeTheCost={data.can.view_cost_price} />;
}
