import type { ReactNode } from 'react';
import { AlertCircle, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from './Button';

export function EmptyState({ icon, title, description, action, className }: { icon?: ReactNode; title: ReactNode; description?: ReactNode; action?: ReactNode; className?: string }) {
    return (
        <div className={cn('flex flex-col items-center justify-center rounded-[12px] border border-dashed border-gray-300 bg-white px-6 py-14 text-center', className)}>
            {icon && <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-accent">{icon}</div>}
            <h3 className="text-base font-semibold text-ink">{title}</h3>
            {description && <p className="mt-1.5 max-w-md text-sm text-muted">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

export function Spinner({ className }: { className?: string }) {
    return <Loader2 className={cn('h-4 w-4 animate-spin text-accent', className)} />;
}

export function LoadingState({ label = 'Loading…', className }: { label?: string; className?: string }) {
    return (
        <div className={cn('flex items-center justify-center gap-2.5 py-12 text-sm text-muted', className)}>
            <Spinner /> {label}
        </div>
    );
}

export function ErrorState({ title = 'Something went wrong', message, onRetry, className }: { title?: string; message?: ReactNode; onRetry?: () => void; className?: string }) {
    return (
        <div className={cn('flex items-start gap-3 rounded-[12px] border border-red-200 bg-red-50 p-4', className)} role="alert">
            <AlertCircle className="mt-0.5 h-4.5 w-4.5 shrink-0 text-red-600" />
            <div className="flex-1">
                <p className="text-sm font-medium text-red-800">{title}</p>
                {message && <p className="mt-0.5 text-sm text-red-700">{message}</p>}
            </div>
            {onRetry && (
                <Button size="sm" variant="secondary" onClick={onRetry}>
                    Retry
                </Button>
            )}
        </div>
    );
}

export function Notice({ tone = 'info', icon, title, children, action, className }: { tone?: 'info' | 'warning' | 'success'; icon?: ReactNode; title?: ReactNode; children?: ReactNode; action?: ReactNode; className?: string }) {
    const styles = {
        info: 'border-accent/20 bg-accent-soft/60 text-ink',
        warning: 'border-amber-200 bg-amber-50 text-amber-900',
        success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    }[tone];
    return (
        <div className={cn('flex items-start gap-3 rounded-[12px] border px-4 py-3', styles, className)}>
            {icon && <span className="mt-0.5 shrink-0">{icon}</span>}
            <div className="min-w-0 flex-1 text-sm">
                {title && <p className="font-medium">{title}</p>}
                {children && <div className={cn(title ? 'mt-0.5' : '', 'opacity-90')}>{children}</div>}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

export function ProgressBar({ value, className }: { value: number; className?: string }) {
    return (
        <div className={cn('h-1.5 w-full overflow-hidden rounded-full bg-gray-100', className)}>
            <div className="h-full rounded-full bg-accent transition-[width] duration-200" style={{ width: `${Math.max(0, Math.min(100, value))}%` }} />
        </div>
    );
}
