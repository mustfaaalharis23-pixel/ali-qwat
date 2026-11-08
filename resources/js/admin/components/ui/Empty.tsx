import type { ReactNode } from 'react';
import { Inbox } from 'lucide-react';

export function Empty({ title, description, icon, action }: { title: ReactNode; description?: ReactNode; icon?: ReactNode; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            <div className="mb-3 rounded-full bg-subtle p-3 text-faint">{icon ?? <Inbox className="size-5" />}</div>
            <p className="text-sm font-medium">{title}</p>
            {description && <p className="mt-1 max-w-sm text-[13px] text-muted">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
