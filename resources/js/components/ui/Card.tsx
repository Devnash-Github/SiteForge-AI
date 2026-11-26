import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Card({ className, children, padded = true }: { className?: string; children: ReactNode; padded?: boolean }) {
    return <div className={cn('rounded-[12px] border border-line bg-white shadow-card', padded && 'p-6', className)}>{children}</div>;
}

export function CardHeader({ title, description, action, className }: { title: ReactNode; description?: ReactNode; action?: ReactNode; className?: string }) {
    return (
        <div className={cn('mb-5 flex items-start justify-between gap-4', className)}>
            <div className="min-w-0">
                <h2 className="text-[15px] font-semibold text-ink">{title}</h2>
                {description && <p className="mt-1 text-sm text-muted">{description}</p>}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

export function PageHeader({ title, description, actions }: { title: ReactNode; description?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight text-ink">{title}</h1>
                {description && <p className="mt-1.5 text-sm text-muted">{description}</p>}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
