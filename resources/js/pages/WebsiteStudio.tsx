import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Check, ClipboardCheck, Cloud, Eye, Globe, Layers, Loader2, Monitor, Palette, RotateCw, Smartphone, Sparkles, Image as ImageIcon } from 'lucide-react';
import { Button, LinkButton } from '@/components/ui/Button';
import { ErrorState, Notice } from '@/components/ui/States';
import { Tooltip } from '@/components/ui/Tooltip';
import { useFlashToasts } from '@/layouts/DashboardLayout';
import { GenerateDialog } from '@/components/GenerateDialog';
import { useStudio } from '@/components/studio/useStudio';
import { SectionsPanel } from '@/components/studio/SectionsPanel';
import { DesignPanel } from '@/components/studio/DesignPanel';
import { AssetsPanel } from '@/components/studio/AssetsPanel';
import { RegenerateDialog } from '@/components/studio/RegenerateDialog';
import { PublishDialog } from '@/components/studio/PublishDialog';
import { WebsiteRenderer } from '@/website-renderer/WebsiteRenderer';
import { cn } from '@/lib/utils';
import type { Asset, MediaItem, RendererData, Section, StudioWebsite, Theme } from '@/types';

interface Props {
    website: StudioWebsite;
    renderer: RendererData;
    media: MediaItem[];
    presets: Record<string, Theme>;
    sectionTypes: { type: string; label: string }[];
    aiAvailable: boolean;
    tones: string[];
    limits: { max_upload_mb: number };
}

type Panel = 'sections' | 'design' | 'assets';

function SaveIndicator({ state, onRetry }: { state: 'saved' | 'saving' | 'error'; onRetry: () => void }) {
    if (state === 'saving')
        return (
            <span className="flex items-center gap-1.5 text-xs text-muted">
                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
            </span>
        );
    if (state === 'error')
        return (
            <button onClick={onRetry} className="flex items-center gap-1.5 rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                <AlertCircle className="h-3.5 w-3.5" /> Not saved · Retry
            </button>
        );
    return (
        <span className="flex items-center gap-1.5 text-xs text-muted">
            <Cloud className="h-3.5 w-3.5" /> Saved
        </span>
    );
}

function Editor(props: Props) {
    const { renderer, presets, sectionTypes, aiAvailable, tones, limits } = props;
    const [website, setWebsite] = useState(props.website);
    const studio = useStudio(website.id, renderer, website.has_unpublished_changes);
    const [media, setMedia] = useState<MediaItem[]>(props.media);
    const [panel, setPanel] = useState<Panel>('sections');
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [regenerating, setRegenerating] = useState<Section | null>(null);
    const [publishOpen, setPublishOpen] = useState(() => typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('publish') === '1');
    const [generateOpen, setGenerateOpen] = useState(false);
    const canvas = useRef<HTMLDivElement>(null);

    const extraAssets: Asset[] = useMemo(() => media.map((m) => ({ id: m.id, url: m.url, alt: m.alt_text ?? '', width: m.width, height: m.height })), [media]);

    useEffect(() => {
        if (!selectedId || !canvas.current) return;
        canvas.current.querySelector(`[data-section-id="${selectedId}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, [selectedId]);

    const openPreview = async () => {
        await studio.flush();
        window.open(`/websites/${website.id}/preview`, '_blank', 'noopener');
    };

    const selectSection = (id: number | null) => {
        setSelectedId(id);
        if (id) setPanel('sections');
    };

    const empty = studio.data.sections.length === 0;

    return (
        <div className="flex h-screen flex-col bg-canvas">
            <Head title={`Editing · ${website.name}`} />

            <header className="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-line bg-white px-3">
                <div className="flex min-w-0 items-center gap-3">
                    <Tooltip label="Back to dashboard" side="bottom">
                        <Link href="/dashboard" className="rounded-lg p-2 text-muted hover:bg-gray-100 hover:text-ink" aria-label="Back to dashboard">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Tooltip>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-ink">{website.name}</p>
                        <div className="flex items-center gap-2">
                            <SaveIndicator state={studio.saveState} onRetry={studio.retry} />
                            {website.is_published && (
                                <span className={cn('text-xs', studio.unpublished ? 'text-amber-700' : 'text-emerald-700')}>· {studio.unpublished ? 'Unpublished changes' : 'Live'}</span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="hidden items-center rounded-[10px] bg-gray-100 p-1 md:flex" role="group" aria-label="Preview size">
                    {(
                        [
                            ['desktop', Monitor, 'Desktop'],
                            ['mobile', Smartphone, 'Mobile'],
                        ] as const
                    ).map(([key, Icon, label]) => (
                        <button
                            key={key}
                            onClick={() => setDevice(key)}
                            aria-pressed={device === key}
                            className={cn('flex items-center gap-1.5 rounded-[8px] px-3 py-1.5 text-[13px] font-medium', device === key ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink')}
                        >
                            <Icon className="h-4 w-4" /> {label}
                        </button>
                    ))}
                </div>

                <div className="flex items-center gap-2">
                    <Tooltip label="Review business facts" side="bottom">
                        <LinkButton href={`/businesses/${website.business.id}/research`} variant="ghost" size="icon" icon={<ClipboardCheck className="h-4 w-4" />} />
                    </Tooltip>
                    {!empty && (
                        <Button variant="ghost" size="sm" icon={<Sparkles className="h-4 w-4" />} onClick={() => setGenerateOpen(true)} className="hidden lg:inline-flex">
                            Regenerate
                        </Button>
                    )}
                    <Button variant="secondary" size="sm" icon={<Eye className="h-4 w-4" />} onClick={openPreview} disabled={empty}>
                        Preview
                    </Button>
                    <Button size="sm" icon={<Globe className="h-4 w-4" />} onClick={() => setPublishOpen(true)} disabled={empty}>
                        {website.is_published ? (studio.unpublished ? 'Update site' : 'Published') : 'Publish'}
                    </Button>
                </div>
            </header>

            <div className="flex min-h-0 flex-1">
                <aside className="flex w-[340px] shrink-0 flex-col border-r border-line bg-white">
                    <div className="flex border-b border-line px-2" role="tablist">
                        {(
                            [
                                ['sections', Layers, 'Sections'],
                                ['design', Palette, 'Design'],
                                ['assets', ImageIcon, 'Assets'],
                            ] as const
                        ).map(([key, Icon, label]) => (
                            <button
                                key={key}
                                role="tab"
                                aria-selected={panel === key}
                                onClick={() => setPanel(key)}
                                className={cn('-mb-px flex flex-1 items-center justify-center gap-1.5 border-b-2 py-3 text-[13px] font-medium', panel === key ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink')}
                            >
                                <Icon className="h-4 w-4" /> {label}
                            </button>
                        ))}
                    </div>
                    <div className="min-h-0 flex-1">
                        {panel === 'sections' && (
                            <SectionsPanel
                                studio={studio}
                                selectedId={selectedId}
                                onSelect={selectSection}
                                sectionTypes={sectionTypes}
                                media={media}
                                openAssets={() => setPanel('assets')}
                                onRegenerate={setRegenerating}
                                aiAvailable={aiAvailable}
                            />
                        )}
                        {panel === 'design' && <DesignPanel studio={studio} presets={presets} />}
                        {panel === 'assets' && <AssetsPanel studio={studio} businessId={website.business.id} media={media} setMedia={setMedia} maxMb={limits.max_upload_mb} />}
                    </div>
                </aside>

                <main ref={canvas} className="min-w-0 flex-1 overflow-y-auto">
                    <div className="space-y-4 p-6">
                        {website.generation_status === 'failed' && website.generation_error && (
                            <ErrorState title="Website generation failed" message={`${website.generation_error} Your existing content was not changed.`} onRetry={() => setGenerateOpen(true)} />
                        )}
                        {website.generation_mode === 'basic' && aiAvailable === false && !empty && (
                            <Notice tone="info" icon={<Check className="h-4 w-4" />}>
                                This site was built from your facts without AI. Add a Gemini or Groq API key on the server to have AI write and rewrite your copy.
                            </Notice>
                        )}
                        {empty ? (
                            <div className="mx-auto mt-16 max-w-md rounded-[14px] border border-line bg-white p-8 text-center shadow-card">
                                <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-accent">
                                    <Sparkles className="h-5 w-5" />
                                </div>
                                <h2 className="text-lg font-semibold text-ink">Generate your website</h2>
                                <p className="mt-1.5 text-sm text-muted">We’ll build the page from your confirmed facts. You can edit everything afterwards.</p>
                                <div className="mt-6 flex justify-center gap-2">
                                    <LinkButton href={`/businesses/${website.business.id}/research`} variant="secondary">
                                        Review facts
                                    </LinkButton>
                                    <Button icon={<Sparkles className="h-4 w-4" />} onClick={() => setGenerateOpen(true)}>
                                        Generate
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div
                                className={cn('mx-auto overflow-hidden border border-line bg-white shadow-card transition-[max-width] duration-300', device === 'mobile' ? 'max-w-[390px] rounded-[24px]' : 'max-w-[1200px] rounded-[12px]')}
                            >
                                <WebsiteRenderer data={studio.data} editing selectedId={selectedId} onSelect={selectSection} extraAssets={extraAssets} />
                            </div>
                        )}
                    </div>
                </main>
            </div>

            <RegenerateDialog section={regenerating} websiteId={website.id} studio={studio} extraAssets={extraAssets} onClose={() => setRegenerating(null)} />
            <PublishDialog open={publishOpen} onClose={() => setPublishOpen(false)} website={website} studio={studio} onPublished={(w) => setWebsite((prev) => ({ ...prev, ...w }))} />
            <GenerateDialog
                open={generateOpen}
                onClose={() => setGenerateOpen(false)}
                websiteId={website.id}
                aiAvailable={aiAvailable}
                tones={tones}
                replacing={!empty}
                defaults={{ tone: website.generation_preferences?.tone, instructions: website.generation_preferences?.instructions }}
            />
        </div>
    );
}

/** While the site is being generated, show progress and poll; then mount the editor with fresh data. */
export default function WebsiteStudio(props: Props) {
    useFlashToasts();
    const generating = props.website.generation_status === 'pending' || props.website.generation_status === 'processing';

    useEffect(() => {
        if (!generating) return;
        const t = setInterval(() => router.reload({ only: ['website', 'renderer', 'media'] }), 2500);
        return () => clearInterval(t);
    }, [generating]);

    if (generating) {
        return (
            <div className="flex h-screen flex-col items-center justify-center bg-canvas px-4">
                <Head title="Generating website" />
                <div className="w-full max-w-sm rounded-[14px] border border-line bg-white p-8 text-center shadow-card">
                    <Loader2 className="mx-auto h-8 w-8 animate-spin text-accent" />
                    <h1 className="mt-5 text-lg font-semibold text-ink">Building {props.website.business.name}’s website</h1>
                    <p className="mt-1.5 text-sm text-muted">Writing copy from your confirmed facts and laying out the sections. This usually takes under a minute.</p>
                    <p className="mt-5 text-xs text-muted">If this doesn’t finish, make sure the queue worker is running.</p>
                    <Link href="/dashboard" className="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-accent hover:underline">
                        <RotateCw className="h-3.5 w-3.5" /> Continue in the background
                    </Link>
                </div>
            </div>
        );
    }

    // Re-mount the editor when a new generation lands so local state starts from the new content.
    return <Editor key={`${props.website.generation_status}-${props.renderer.sections.map((s) => s.id).join('.')}`} {...props} />;
}
