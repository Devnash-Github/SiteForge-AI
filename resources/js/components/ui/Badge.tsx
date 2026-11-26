import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Tone = 'gray' | 'green' | 'amber' | 'red' | 'blue' | 'violet';

const tones: Record<Tone, string> = {
    gray: 'bg-gray-100 text-gray-700',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
    amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    red: 'bg-red-50 text-red-700 ring-red-600/15',
    blue: 'bg-sky-50 text-sky-700 ring-sky-600/15',
    violet: 'bg-accent-soft text-accent ring-accent/20',
};

export function Badge({ tone = 'gray', children, className, dot }: { tone?: Tone; children: ReactNode; className?: string; dot?: boolean }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-transparent ring-inset', tones[tone], className)}>
            {dot && <span className="h-1.5 w-1.5 rounded-full bg-current" />}
            {children}
        </span>
    );
}

const websiteStatus: Record<string, { tone: Tone; label: string }> = {
    published: { tone: 'green', label: 'Published' },
    draft: { tone: 'gray', label: 'Draft' },
    unpublished: { tone: 'amber', label: 'Unpublished' },
};

export function WebsiteStatusBadge({ status }: { status: string }) {
    const s = websiteStatus[status] ?? websiteStatus.draft;
    return (
        <Badge tone={s.tone} dot>
            {s.label}
        </Badge>
    );
}

const sourceStatus: Record<string, { tone: Tone; label: string }> = {
    pending: { tone: 'gray', label: 'Pending' },
    processing: { tone: 'blue', label: 'Processing' },
    completed: { tone: 'green', label: 'Completed' },
    failed: { tone: 'red', label: 'Failed' },
    blocked: { tone: 'amber', label: 'Blocked' },
};

export function SourceStatusBadge({ status }: { status: string }) {
    const s = sourceStatus[status] ?? sourceStatus.pending;
    return <Badge tone={s.tone}>{s.label}</Badge>;
}

const factStatus: Record<string, { tone: Tone; label: string }> = {
    extracted: { tone: 'blue', label: 'Extracted' },
    needs_review: { tone: 'amber', label: 'Needs review' },
    confirmed: { tone: 'green', label: 'Confirmed' },
    rejected: { tone: 'gray', label: 'Rejected' },
};

export function FactStatusBadge({ status }: { status: string }) {
    const s = factStatus[status] ?? factStatus.extracted;
    return <Badge tone={s.tone}>{s.label}</Badge>;
}
