import { Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { api } from '../../lib/api';
import { cn } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Skeleton } from '../../components/ui';
import { InfoButton } from '../../components/MetricCard';
import { PageHeader } from '../../components/PageHeader';
import { QueryError } from '../../pages/NotFound';

interface LensCard {
    key: string;
    label: string;
    explanation: string;
    guidance: string;
    count: number;
    number: string;
    path: string;
    needs_attention: boolean;
}

export function MonitoringDashboardPage() {
    const t = useT();
    const { data, error } = useQuery({ queryKey: ['dashboard', 'monitoring'], queryFn: () => api.get<{ title: string; cards: LensCard[] }>('/dashboard/monitoring') });

    if (error) return <QueryError error={error} />;

    return (
        <>
            <PageHeader title={data?.title ?? t('filament/pages/monitoring_dashboard.title')} />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {!data && Array.from({ length: 6 }).map((_, index) => <Skeleton key={index} className="h-32 rounded-[var(--radius-card)]" />)}
                {data?.cards.map((card) => (
                    <div key={card.key} className={cn('card flex flex-col gap-3 p-4.5', card.needs_attention && 'border-warning/40')}>
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex items-center gap-2">
                                {card.needs_attention ? <TriangleAlert className="size-4.5 text-warning" /> : <CircleCheck className="size-4.5 text-success" />}
                                <p className="text-sm font-medium">{card.label}</p>
                            </div>
                            <InfoButton title={card.label} explanation={card.explanation} example={card.guidance} />
                        </div>
                        <div className="flex items-end justify-between gap-2">
                            <p className={cn('text-3xl font-semibold tabular-nums', card.needs_attention ? 'text-ink' : 'text-faint')}>{card.number}</p>
                            <Link to={card.path} className="text-[13px] font-medium text-accent hover:underline">
                                {t('admin.monitoring.open_list')}
                            </Link>
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}
