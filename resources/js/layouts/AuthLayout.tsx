import type { ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import { Logo } from '@/components/Logo';
import { useFlashToasts } from './DashboardLayout';

export default function AuthLayout({ title, subtitle, children, footer }: { title: string; subtitle?: string; children: ReactNode; footer?: ReactNode }) {
    useFlashToasts();
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-canvas px-4 py-12">
            <Head title={title} />
            <div className="mb-8">
                <Logo size="lg" />
            </div>
            <div className="w-full max-w-[400px] rounded-[14px] border border-line bg-white p-8 shadow-card">
                <h1 className="text-xl font-semibold tracking-tight text-ink">{title}</h1>
                {subtitle && <p className="mt-1.5 text-sm text-muted">{subtitle}</p>}
                <div className="mt-7">{children}</div>
            </div>
            {footer && <div className="mt-6 text-sm text-muted">{footer}</div>}
            <p className="mt-10 max-w-sm text-center text-xs text-muted">Turn your existing business information into a professional website with AI.</p>
        </div>
    );
}
