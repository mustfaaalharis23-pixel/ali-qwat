import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { CopyPlus, Eye, EyeOff, Plus, ShieldCheck, UserCircle } from 'lucide-react';
import { api } from '../../lib/api';
import { useForm, useSave } from '../../lib/form';
import { useT } from '../../lib/i18n';
import { Badge, Button, ButtonLink, DescriptionList, Field, Input, Modal, PageSpinner, Skeleton, toast, Toggle } from '../../components/ui';
import { DataTable } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { SearchSelect } from '../../components/SearchSelect';
import type { Abilities } from '../../components/RecordActions';
import { QueryError } from '../../pages/NotFound';
import { PermissionTree, type PermissionGroup } from './PermissionTree';
import { BoolIcon, BulkActions, RecordHeaderActions, RecordTabs, Section, useIsSuper, useRowMenu } from './shared';

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_cashier: boolean;
    is_super?: boolean;
    permissions?: string[];
    deleted_at: string | null;
    can: Abilities;
}

const PATH = '/users';

function usePermissionGroups(enabled: boolean) {
    return useQuery({ queryKey: ['options', 'permission-groups'], queryFn: () => api.get<{ data: PermissionGroup[] }>('/users/permission-groups'), enabled, staleTime: 5 * 60_000 });
}

export function UsersPage() {
    const t = useT();
    const isSuper = useIsSuper();
    const menu = useRowMenu<User>(PATH, PATH, (user) => user.name);

    return (
        <>
            <PageHeader title={t('filament/resources/user.plural_label')} />
            <DataTable<User>
                endpoint={PATH}
                trashed
                selectable
                bulkActions={(selected, clear) => <BulkActions endpoint={PATH} selected={selected} clear={clear} forceDelete={false} />}
                toolbar={(response) =>
                    (response?.can as { create?: boolean } | undefined)?.create && (
                        <ButtonLink to={`${PATH}/create`} variant="primary" size="sm" icon={<Plus className="size-4" />}>
                            {t('admin.actions.new')}
                        </ButtonLink>
                    )
                }
                defaultSort={{ column: 'id', direction: 'asc' }}
                rowHref={(user) => `${PATH}/${user.id}`}
                rowActions={(user) => menu.items(user)}
                columns={[
                    { key: 'id', header: '#', sortable: true, className: 'w-16', cell: (user) => <span className="text-muted tabular-nums">{user.id}</span> },
                    {
                        key: 'name',
                        header: t('filament/resources/user.fields.name'),
                        sortable: true,
                        cell: (user) => (
                            <span className="inline-flex items-center gap-2 font-medium">
                                {user.name}
                                {!user.is_active && <Badge>{t('filament/resources/user.fields.is_active')}: ✕</Badge>}
                                {user.deleted_at && <Badge tone="danger">{t('admin.actions.deleted')}</Badge>}
                            </span>
                        ),
                    },
                    { key: 'email', header: t('filament/resources/user.fields.email'), sortable: true, cell: (user) => <span className="text-muted">{user.email}</span> },
                    { key: 'is_super', header: t('filament/resources/user.fields.is_super'), align: 'center', hidden: !isSuper, cell: (user) => <span className="inline-flex"><BoolIcon value={Boolean(user.is_super)} /></span> },
                    { key: 'is_cashier', header: t('filament/resources/user.fields.is_cashier'), align: 'center', cell: (user) => <span className="inline-flex"><BoolIcon value={user.is_cashier} /></span> },
                ]}
            />
            {menu.dialogs}
        </>
    );
}

export function UserFormPage() {
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: User }>(`${PATH}/${id}`), enabled: Boolean(id) });
    if (id && isLoading) return <PageSpinner />;
    if (id && (error || !data)) return <QueryError error={error} />;
    return <UserForm user={data?.data ?? null} />;
}

/**
 * The account, its status and role, and (for a super user, on an existing user) its permission tree with a copy from another user.
 * The password is required for a new user and kept on an edit when it is left empty.
 */
function UserForm({ user }: { user: User | null }) {
    const t = useT();
    const navigate = useNavigate();
    const isSuper = useIsSuper();
    const [revealed, setRevealed] = useState(false);
    const [copying, setCopying] = useState(false);
    const showPermissions = isSuper && user !== null;
    const groups = usePermissionGroups(showPermissions);
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        is_active: user?.is_active ?? true,
        is_cashier: user?.is_cashier ?? false,
        is_super: user?.is_super ?? false,
        permissions: user?.permissions ?? [],
    });
    const save = useSave(
        (body: typeof form.values) => {
            const { permissions, is_super, ...rest } = body;
            const payload = { ...rest, ...(isSuper ? { is_super } : {}), ...(showPermissions ? { permissions } : {}) };
            return user ? api.put<{ data: User }>(`${PATH}/${user.id}`, payload) : api.post<{ data: User }>(PATH, payload);
        },
        { invalidate: ['list', 'record', 'options'], success: t('admin.actions.saved'), onSuccess: (result) => navigate(`${PATH}/${result.data.id}`), onError: form.fail },
    );

    return (
        <form onSubmit={(event) => { event.preventDefault(); save.mutate(form.values); }}>
            <PageHeader
                title={user ? user.name : `${t('admin.actions.create')} ${t('filament/resources/user.label')}`}
                breadcrumbs={[{ label: t('filament/resources/user.plural_label'), to: PATH }, { label: user ? t('admin.actions.edit') : t('admin.actions.create') }]}
                actions={user && <RecordHeaderActions record={user} endpoint={PATH} path={PATH} label={user.name} edit={false} />}
            />
            <div className="space-y-5">
                <Section title={t('filament/resources/user.sections.account')} icon={<UserCircle className="size-4" />}>
                    <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
                        <Field label={t('filament/resources/user.fields.name')} error={form.errors.name} required>
                            {(fieldId) => <Input id={fieldId} autoFocus value={form.values.name} maxLength={255} onChange={(event) => form.set('name', event.target.value)} invalid={Boolean(form.errors.name)} />}
                        </Field>
                        <Field label={t('filament/resources/user.fields.email')} error={form.errors.email} required>
                            {(fieldId) => <Input id={fieldId} type="email" dir="ltr" value={form.values.email} maxLength={255} onChange={(event) => form.set('email', event.target.value)} invalid={Boolean(form.errors.email)} />}
                        </Field>
                        <Field label={t('filament/resources/user.fields.password')} error={form.errors.password} required={!user} hint={user ? t('admin/system.users.password_keep') : undefined}>
                            {(fieldId) => (
                                <div className="relative">
                                    <Input
                                        id={fieldId}
                                        type={revealed ? 'text' : 'password'}
                                        dir="ltr"
                                        autoComplete="new-password"
                                        value={form.values.password}
                                        onChange={(event) => form.set('password', event.target.value)}
                                        invalid={Boolean(form.errors.password)}
                                        className="pe-10"
                                    />
                                    <button type="button" onClick={() => setRevealed((value) => !value)} className="absolute end-2 top-1/2 -translate-y-1/2 rounded p-1 text-faint hover:text-ink" aria-label={t('filament/resources/user.fields.password')}>
                                        {revealed ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                            )}
                        </Field>
                    </div>
                </Section>
                <Section title={t('filament/resources/user.sections.role')} icon={<ShieldCheck className="size-4" />}>
                    <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
                        <Toggle checked={form.values.is_active} onChange={(checked) => form.set('is_active', checked)} label={t('filament/resources/user.fields.is_active')} />
                        <Toggle checked={form.values.is_cashier} onChange={(checked) => form.set('is_cashier', checked)} label={t('filament/resources/user.fields.is_cashier')} />
                        {isSuper && (
                            <div>
                                <Toggle checked={form.values.is_super} onChange={(checked) => form.set('is_super', checked)} label={t('filament/resources/user.fields.is_super')} />
                                <p className="mt-1 ms-11.5 text-xs text-muted">{t('filament/resources/user.hints.is_super')}</p>
                            </div>
                        )}
                    </div>
                </Section>
                {showPermissions && (
                    <Section
                        title={t('permissions.section')}
                        actions={
                            <>
                                <Button size="sm" variant="ghost" onClick={() => form.set('permissions', (groups.data?.data ?? []).flatMap((group) => group.permissions.map((permission) => permission.name)))}>
                                    {t('admin/system.users.select_all')}
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => form.set('permissions', [])}>
                                    {t('admin/system.users.clear_all')}
                                </Button>
                                <Button size="sm" icon={<CopyPlus className="size-4" />} onClick={() => setCopying(true)}>
                                    {t('permissions.copy.label')}
                                </Button>
                            </>
                        }
                    >
                        <p className="mb-4 text-[13px] text-muted">{form.values.is_super ? t('admin/system.users.permissions_super') : t('admin/system.users.permissions_hint')}</p>
                        {groups.data ? (
                            <PermissionTree groups={groups.data.data} value={form.values.permissions} onChange={(value) => form.set('permissions', value)} />
                        ) : (
                            <Skeleton className="h-40 w-full" />
                        )}
                    </Section>
                )}
                <div className="flex justify-end gap-2">
                    <ButtonLink to={user ? `${PATH}/${user.id}` : PATH}>{t('admin.actions.cancel')}</ButtonLink>
                    <Button type="submit" variant="primary" loading={save.isPending}>
                        {user ? t('admin.actions.save_changes') : t('admin.actions.create')}
                    </Button>
                </div>
            </div>
            {copying && user && <CopyPermissionsModal userId={user.id} onClose={() => setCopying(false)} onCopied={(permissions) => form.set('permissions', permissions)} />}
        </form>
    );
}

/** Copies another user's saved permissions onto the tree; they are applied when the form is saved. */
function CopyPermissionsModal({ userId, onClose, onCopied }: { userId: number; onClose: () => void; onCopied: (permissions: string[]) => void }) {
    const t = useT();
    const [source, setSource] = useState<number | null>(null);
    const [loading, setLoading] = useState(false);

    const copy = async () => {
        if (source === null) return;
        setLoading(true);
        try {
            const result = await api.get<{ data: string[] }>(`/users/${source}/permissions`);
            onCopied(result.data);
            toast.success(t('permissions.copy.copied'));
            onClose();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : String(error));
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="sm"
            title={t('permissions.copy.label')}
            footer={
                <>
                    <Button onClick={onClose}>{t('admin.actions.cancel')}</Button>
                    <Button variant="primary" loading={loading} disabled={source === null} onClick={copy}>
                        {t('admin.actions.confirm')}
                    </Button>
                </>
            }
        >
            <Field label={t('permissions.copy.user')} required>
                {(fieldId) => (
                    <SearchSelect
                        id={fieldId}
                        endpoint="/options/users"
                        value={source}
                        onChange={(value) => setSource(value === null || Number(value) === userId ? null : Number(value))}
                    />
                )}
            </Field>
        </Modal>
    );
}

export function UserPage() {
    const t = useT();
    const isSuper = useIsSuper();
    const { id } = useParams();
    const { data, isLoading, error } = useQuery({ queryKey: ['record', PATH, id], queryFn: () => api.get<{ data: User }>(`${PATH}/${id}`) });
    const groups = usePermissionGroups(isSuper);

    if (isLoading) return <PageSpinner />;
    if (error || !data) return <QueryError error={error} />;
    const user = data.data;

    return (
        <>
            <PageHeader
                title={user.name}
                breadcrumbs={[{ label: t('filament/resources/user.plural_label'), to: PATH }, { label: user.name }]}
                badge={user.deleted_at ? <Badge tone="danger">{t('admin.actions.deleted')}</Badge> : user.is_super ? <Badge tone="accent">{t('filament/resources/user.fields.is_super')}</Badge> : undefined}
                actions={<RecordHeaderActions record={user} endpoint={PATH} path={PATH} label={user.name} />}
            />
            <RecordTabs
                subjectType="user"
                subjectId={user.id}
                tabs={[
                    {
                        value: 'details',
                        label: t('admin/system.tabs.details'),
                        content: (
                            <div className="space-y-5">
                                <Section title={t('filament/resources/user.sections.account')} icon={<UserCircle className="size-4" />}>
                                    <DescriptionList
                                        items={[
                                            { label: t('filament/resources/user.fields.name'), value: user.name },
                                            { label: t('filament/resources/user.fields.email'), value: <span dir="ltr">{user.email}</span> },
                                        ]}
                                    />
                                </Section>
                                <Section title={t('filament/resources/user.sections.role')} icon={<ShieldCheck className="size-4" />}>
                                    <DescriptionList
                                        items={[
                                            { label: t('filament/resources/user.fields.is_active'), value: <BoolIcon value={user.is_active} /> },
                                            { label: t('filament/resources/user.fields.is_cashier'), value: <BoolIcon value={user.is_cashier} /> },
                                            { label: t('filament/resources/user.fields.is_super'), value: <BoolIcon value={Boolean(user.is_super)} />, hidden: !isSuper },
                                        ]}
                                    />
                                </Section>
                                {isSuper && (
                                    <Section title={t('permissions.section')}>
                                        {groups.data ? <PermissionTree groups={groups.data.data} value={user.permissions ?? []} disabled /> : <Skeleton className="h-40 w-full" />}
                                    </Section>
                                )}
                            </div>
                        ),
                    },
                ]}
            />
        </>
    );
}
