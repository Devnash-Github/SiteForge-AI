import { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, ArrowRight, CircleSlash, Globe2, Info, Pencil, Plus, Search, Sparkles } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Button, LinkButton } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Notice, Spinner } from '@/components/ui/States';
import { SourcesPanel } from '@/components/research/SourcesPanel';
import { ConflictsPanel } from '@/components/research/ConflictsPanel';
import { AddFactDialog, FactsPanel } from '@/components/research/FactsPanel';
import { GenerateDialog } from '@/components/GenerateDialog';
import type { BusinessSummary, Conflict, FactItem, SourceItem } from '@/types';

interface Props {
    business: BusinessSummary;
    sources: SourceItem[];
    facts: FactItem[];
    conflicts: Conflict[];
    conflictKeys: string[];
    missing: { key: string; label: string }[];
    factKeys: { key: string; label: string; single: boolean }[];
    research: { available: boolean; provider: string; status: string; error: string | null };
    processing: boolean;
    queueStalled: boolean;
    aiAvailable: boolean;
    website: { id: number; generation_status: string; has_content: boolean } | null;
    limits: { max_upload_mb: number };
}

const POLL_PROPS = ['business', 'sources', 'facts', 'conflicts', 'conflictKeys', 'missing', 'research', 'processing', 'queueStalled', 'website'];

export default function ResearchReview(props: Props) {
    const { business, sources, facts, conflicts, conflictKeys, missing, factKeys, research, processing, queueStalled, aiAvailable, website } = props;
    const [addKey, setAddKey] = useState<string | undefined>();
    const [adding, setAdding] = useState(false);
    const [generating, setGenerating] = useState(false);

    useEffect(() => {
        if (!processing) return;
        const t = setInterval(() => router.reload({ only: POLL_PROPS }), 3000);
        return () => clearInterval(t);
    }, [processing]);

    const confirmed = facts.filter((f) => f.status === 'confirmed');
    const toReview = facts.filter((f) => f.status === 'extracted' || f.status === 'needs_review').length;
    const uncertain = sources.filter((s) => s.identity_status === 'uncertain').length;

    const warnings: string[] = [];
    if (conflicts.length) warnings.push(`${conflicts.length} conflict${conflicts.length > 1 ? 's' : ''} not resolved — conflicting values are left off the website until you choose.`);
    if (toReview) warnings.push(`${toReview} fact${toReview > 1 ? 's are' : ' is'} not confirmed yet and won't be used.`);
    if (processing) warnings.push('Some sources are still being processed. You can generate now or wait for more facts.');

    const openAdd = (key?: string) => {
        setAddKey(key);
        setAdding(true);
    };

    return (
        <DashboardLayout title={`Review · ${business.name}`} wide>
            <LinkButton href="/dashboard" variant="ghost" size="sm" icon={<ArrowLeft className="h-4 w-4" />} className="-ml-3 mb-4">
                Dashboard
            </LinkButton>

            <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-medium tracking-wide text-accent uppercase">Step 2 of 3 · Review information</p>
                    <h1 className="mt-1.5 text-2xl font-semibold tracking-tight text-ink">{business.name}</h1>
                    <p className="mt-1 text-sm text-muted">
                        {business.city}
                        {business.category ? ` · ${business.category}` : ''} ·{' '}
                        <Link href={`/businesses/${business.id}`} className="inline-flex items-center gap-1 text-accent hover:underline">
                            <Pencil className="h-3 w-3" /> Edit details
                        </Link>
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {website?.has_content && (
                        <LinkButton href={`/websites/${website.id}/studio`} variant="secondary">
                            Open editor
                        </LinkButton>
                    )}
                    {website && (
                        <Button size="lg" icon={<Sparkles className="h-4 w-4" />} onClick={() => setGenerating(true)}>
                            {website.has_content ? 'Regenerate website' : 'Generate website'}
                        </Button>
                    )}
                </div>
            </div>

            <div className="mb-6 space-y-3">
                {processing && (
                    <Notice tone="info" icon={<Spinner className="h-4 w-4" />} title="Reading your sources…">
                        Facts appear below as each source is processed. This page updates automatically.
                    </Notice>
                )}
                {queueStalled && (
                    <Notice tone="warning" icon={<AlertTriangle className="h-4 w-4" />} title="Waiting for the background worker">
                        Processing hasn’t started yet. Make sure the queue worker is running (<code>php artisan queue:work</code>).
                    </Notice>
                )}
                {!research.available ? (
                    <Notice tone="info" icon={<CircleSlash className="h-4 w-4" />} title="Web research is unavailable">
                        No search provider is configured, so we only use the information and links you provided. You can still build your website.
                    </Notice>
                ) : research.status === 'failed' ? (
                    <Notice tone="warning" icon={<AlertTriangle className="h-4 w-4" />} title="Web research failed" action={<Button size="sm" variant="secondary" onClick={() => router.post(`/businesses/${business.id}/research`, {}, { preserveScroll: true })}>Try again</Button>}>
                        {research.error}
                    </Notice>
                ) : research.status === 'completed' && research.error ? (
                    <Notice tone="info" icon={<Search className="h-4 w-4" />} title="Web research finished" action={<Button size="sm" variant="ghost" onClick={() => router.post(`/businesses/${business.id}/research`, {}, { preserveScroll: true })}>Search again</Button>}>
                        {research.error}
                    </Notice>
                ) : null}
                {uncertain > 0 && (
                    <Notice tone="warning" icon={<Info className="h-4 w-4" />} title={`We found ${uncertain} result${uncertain > 1 ? 's' : ''} that might be a different business`}>
                        Businesses can share a name. Confirm or reject them in the Sources list — their information isn’t used until you do.
                    </Notice>
                )}
            </div>

            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div className="space-y-8">
                    <ConflictsPanel conflicts={conflicts} businessId={business.id} />
                    <FactsPanel facts={facts} businessId={business.id} conflictKeys={conflictKeys} onAdd={() => openAdd()} />
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Summary" />
                        <dl className="grid grid-cols-3 gap-3 text-center">
                            {[
                                ['Confirmed', confirmed.length],
                                ['To review', toReview],
                                ['Conflicts', conflicts.length],
                            ].map(([label, n]) => (
                                <div key={label} className="rounded-[10px] bg-gray-50 px-2 py-3">
                                    <dt className="text-xs text-muted">{label}</dt>
                                    <dd className="mt-0.5 text-lg font-semibold text-ink">{n}</dd>
                                </div>
                            ))}
                        </dl>
                        {missing.length > 0 && (
                            <div className="mt-5">
                                <p className="mb-2 text-[13px] font-medium text-ink">Missing information</p>
                                <ul className="space-y-1.5">
                                    {missing.map((m) => (
                                        <li key={m.key} className="flex items-center justify-between text-sm">
                                            <span className="text-muted">{m.label}</span>
                                            <button onClick={() => openAdd(m.key)} className="flex items-center gap-1 text-[13px] font-medium text-accent hover:underline">
                                                <Plus className="h-3.5 w-3.5" /> Add
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-3 text-xs text-muted">Missing details are simply left off the website — nothing is made up.</p>
                            </div>
                        )}
                        {website && (
                            <Button className="mt-6 w-full" onClick={() => setGenerating(true)} icon={<Sparkles className="h-4 w-4" />}>
                                {website.has_content ? 'Regenerate website' : 'Generate website'}
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        )}
                    </Card>

                    <SourcesPanel sources={sources} businessId={business.id} maxMb={props.limits.max_upload_mb} />

                    {business.website_url && (
                        <p className="flex items-center gap-1.5 px-1 text-xs text-muted">
                            <Globe2 className="h-3.5 w-3.5" /> We only read public pages and never sign in to any account.
                        </p>
                    )}
                </div>
            </div>

            <AddFactDialog open={adding} onClose={() => setAdding(false)} businessId={business.id} factKeys={factKeys} initialKey={addKey} />
            {website && (
                <GenerateDialog open={generating} onClose={() => setGenerating(false)} websiteId={website.id} aiAvailable={aiAvailable} replacing={website.has_content} warnings={warnings} />
            )}
        </DashboardLayout>
    );
}
