import { isRouteErrorResponse, useRouteError } from 'react-router';
import { ApiError } from '../lib/api';
import { useT } from '../lib/i18n';
import { ButtonLink } from '../components/ui';

export function NotFoundPage() {
    const t = useT();
    return (
        <div className="flex flex-col items-center py-24 text-center">
            <p className="text-5xl font-semibold text-faint">404</p>
            <h1 className="mt-3 text-lg font-semibold">{t('admin.errors.not_found')}</h1>
            <ButtonLink to="/" className="mt-6">
                {t('admin.errors.go_home')}
            </ButtonLink>
        </div>
    );
}

/** What a screen shows when its data could not be read: not found, not allowed, or another failure. */
export function ErrorPage() {
    const t = useT();
    const error = useRouteError();
    const status = error instanceof ApiError ? error.status : isRouteErrorResponse(error) ? error.status : 500;
    const title = status === 403 ? t('admin.errors.forbidden') : status === 404 ? t('admin.errors.not_found') : t('admin.errors.failed');
    return (
        <div className="flex flex-col items-center py-24 text-center">
            <p className="text-5xl font-semibold text-faint">{status}</p>
            <h1 className="mt-3 text-lg font-semibold">{title}</h1>
            {status >= 500 && error instanceof Error && <p className="mt-2 max-w-lg text-sm text-muted">{error.message}</p>}
            <ButtonLink to="/" className="mt-6">
                {t('admin.errors.go_home')}
            </ButtonLink>
        </div>
    );
}

/** Shows a failed query the same way inside a page. */
export function QueryError({ error }: { error: unknown }) {
    const t = useT();
    const status = error instanceof ApiError ? error.status : 500;
    const title = status === 403 ? t('admin.errors.forbidden') : status === 404 ? t('admin.errors.not_found') : t('admin.errors.failed');
    return (
        <div className="card flex flex-col items-center px-6 py-16 text-center">
            <p className="text-3xl font-semibold text-faint">{status}</p>
            <p className="mt-2 font-medium">{title}</p>
            {status >= 500 && error instanceof Error && <p className="mt-1 text-sm text-muted">{error.message}</p>}
        </div>
    );
}
