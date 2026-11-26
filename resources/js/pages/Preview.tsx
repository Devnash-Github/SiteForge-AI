import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Monitor, Smartphone } from 'lucide-react';
import { WebsiteRenderer } from '@/website-renderer/WebsiteRenderer';
import { cn } from '@/lib/utils';
import type { RendererData, StudioWebsite } from '@/types';

export default function Preview({ website, renderer }: { website: StudioWebsite; renderer: RendererData }) {
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    return (
        <div className={cn('min-h-screen', device === 'mobile' ? 'bg-gray-200 py-10' : 'bg-white')}>
            <Head title={`Preview · ${website.name}`} />
            <div className={cn('mx-auto', device === 'mobile' ? 'max-w-[390px] overflow-hidden rounded-[28px] shadow-pop' : '')}>
                <WebsiteRenderer data={renderer} />
            </div>
            <div className="fixed bottom-5 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-line bg-white/95 p-1 shadow-pop backdrop-blur">
                <Link href={`/websites/${website.id}/studio`} className="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium text-muted hover:text-ink">
                    <ArrowLeft className="h-3.5 w-3.5" /> Editor
                </Link>
                <span className="mx-1 h-4 w-px bg-line" />
                {(
                    [
                        ['desktop', Monitor],
                        ['mobile', Smartphone],
                    ] as const
                ).map(([key, Icon]) => (
                    <button key={key} onClick={() => setDevice(key)} aria-label={`${key} preview`} aria-pressed={device === key} className={cn('rounded-full p-2', device === key ? 'bg-accent text-white' : 'text-muted hover:text-ink')}>
                        <Icon className="h-4 w-4" />
                    </button>
                ))}
                <span className="ml-1 pr-3 text-xs text-muted">Preview{website.is_published ? '' : ' · not published'}</span>
            </div>
        </div>
    );
}
