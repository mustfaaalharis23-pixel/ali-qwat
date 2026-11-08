import { useState, type FormEvent } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router';
import { Languages } from 'lucide-react';
import { ApiError } from '../lib/api';
import { cn } from '../lib/format';
import { boot, useI18n } from '../lib/i18n';
import { useLogin, useSession } from '../lib/session';
import { Button, Checkbox, Field, Input } from '../components/ui';

export function LoginPage() {
    const { t, locale, locales, setLocale } = useI18n();
    const { data: session } = useSession();
    const login = useLogin();
    const navigate = useNavigate();
    const location = useLocation();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(true);
    const error = login.error instanceof ApiError ? login.error.first('email') ?? login.error.first() : undefined;
    const from = (location.state as { from?: string } | null)?.from ?? '/';

    if (session?.user) return <Navigate to={from} replace />;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        login.mutate({ email, password, remember }, { onSuccess: () => navigate(from, { replace: true }) });
    };

    return (
        <div className="flex min-h-dvh items-center justify-center px-4 py-12">
            <div className="w-full max-w-sm">
                <div className="mb-8 flex flex-col items-center gap-3 text-center">
                    <div className="flex size-11 items-center justify-center rounded-xl bg-accent text-lg font-bold text-white">{boot.appName.slice(0, 1)}</div>
                    <h1 className="text-xl font-semibold tracking-tight">{t('admin.login.title')}</h1>
                    <p className="text-sm text-muted">{t('admin.login.subtitle')}</p>
                </div>
                <form onSubmit={submit} className="card flex flex-col gap-4 p-6">
                    <Field label={t('admin.login.email')} error={error}>
                        {(id) => <Input id={id} type="email" autoComplete="username" required autoFocus value={email} onChange={(event) => setEmail(event.target.value)} invalid={Boolean(error)} dir="ltr" />}
                    </Field>
                    <Field label={t('admin.login.password')}>
                        {(id) => <Input id={id} type="password" autoComplete="current-password" required value={password} onChange={(event) => setPassword(event.target.value)} dir="ltr" />}
                    </Field>
                    <Checkbox label={t('admin.login.remember')} checked={remember} onChange={setRemember} />
                    <Button type="submit" variant="primary" loading={login.isPending} className="mt-1 w-full">
                        {t('admin.login.submit')}
                    </Button>
                </form>
                <div className="mt-6 flex items-center justify-center gap-1 text-[13px]">
                    <Languages className="me-1 size-4 text-faint" />
                    {Object.entries(locales).map(([value, label]) => (
                        <button key={value} onClick={() => setLocale(value)} className={cn('rounded-md px-2 py-1 hover:bg-subtle', value === locale ? 'font-semibold text-accent' : 'text-muted')}>
                            {label}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
