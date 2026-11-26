import { useState } from 'react';
import { AlertTriangle, RotateCw, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Textarea } from '@/components/ui/Field';
import { Modal } from '@/components/ui/Modal';
import { ErrorState, Notice } from '@/components/ui/States';
import { errorMessage, http } from '@/lib/http';
import type { Asset, RendererData, Section } from '@/types';
import { WebsiteRenderer } from '@/website-renderer/WebsiteRenderer';
import type { Studio } from './useStudio';

const PRESETS = ['Make it shorter', 'Make it more professional', 'Use a friendlier tone', 'Improve the wording', 'Make it more persuasive'];
const LABELS: Record<string, string> = { hero: 'Hero', about: 'About', services: 'Services', gallery: 'Gallery', contact: 'Contact', footer: 'Footer' };

interface Props {
    section: Section | null;
    websiteId: number;
    studio: Studio;
    extraAssets: Asset[];
    onClose: () => void;
}

/**
 * Regenerate one section: the AI result is shown as a proposal next to the
 * current version; nothing changes until the owner clicks "Use this version".
 */
export function RegenerateDialog({ section, websiteId, studio, extraAssets, onClose }: Props) {
    const [instructions, setInstructions] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [proposal, setProposal] = useState<Section['content'] | null>(null);
    const [removed, setRemoved] = useState<string[]>([]);
    const [currentId, setCurrentId] = useState<number | null>(null);

    if (section && section.id !== currentId) {
        setCurrentId(section.id);
        setProposal(null);
        setError(null);
        setRemoved([]);
        setInstructions('');
    }

    const generate = async () => {
        if (!section) return;
        setLoading(true);
        setError(null);
        try {
            await studio.flush();
            const r = await http.post<{ proposal: Section['content']; removed_claims: string[] }>(`/websites/${websiteId}/sections/${section.id}/regenerate`, { instructions });
            setProposal(r.data.proposal);
            setRemoved(r.data.removed_claims);
        } catch (e) {
            setError(errorMessage(e, 'The AI could not rewrite this section. Your current content is unchanged.'));
        } finally {
            setLoading(false);
        }
    };

    const accept = () => {
        if (section && proposal) studio.updateSection(section.id, proposal);
        onClose();
    };

    const preview = (content: Section['content']): RendererData => ({
        ...studio.data,
        sections: section ? [{ ...section, visible: true, content } as Section] : [],
    });

    return (
        <Modal
            open={!!section}
            onClose={onClose}
            size="xl"
            title={section ? `Rewrite ${LABELS[section.type]} with AI` : ''}
            description="Only this section changes. Facts such as contact details and services stay as you confirmed them."
            footer={
                proposal ? (
                    <>
                        <Button variant="ghost" className="mr-auto" icon={<RotateCw className="h-4 w-4" />} onClick={generate} loading={loading}>
                            Try again
                        </Button>
                        <Button variant="secondary" onClick={onClose}>
                            Discard
                        </Button>
                        <Button onClick={accept}>Use this version</Button>
                    </>
                ) : (
                    <>
                        <Button variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button onClick={generate} loading={loading} icon={<Sparkles className="h-4 w-4" />}>
                            Generate
                        </Button>
                    </>
                )
            }
        >
            {section && (
                <div className="space-y-5">
                    <div className="space-y-3">
                        <div className="flex flex-wrap gap-2">
                            {PRESETS.map((p) => (
                                <button
                                    key={p}
                                    onClick={() => setInstructions(p)}
                                    className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${instructions === p ? 'border-accent bg-accent-soft text-accent' : 'border-line text-muted hover:text-ink'}`}
                                >
                                    {p}
                                </button>
                            ))}
                        </div>
                        <Textarea
                            value={instructions}
                            onChange={(e) => setInstructions(e.target.value)}
                            rows={2}
                            maxLength={500}
                            placeholder="Optional: tell the AI what to change, e.g. “mention that we're walk-in friendly”"
                        />
                    </div>

                    {error && <ErrorState title="Rewrite failed" message={error} onRetry={generate} />}

                    {removed.length > 0 && (
                        <Notice tone="warning" icon={<AlertTriangle className="h-4 w-4" />} title="Some claims were left out">
                            The AI wrote statements that aren't in your confirmed facts, so we removed them. Add them as facts on the review page if they're true.
                        </Notice>
                    )}

                    {proposal ? (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {[
                                ['Current', section.content],
                                ['New version', proposal],
                            ].map(([label, content]) => (
                                <div key={label as string}>
                                    <p className="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">{label as string}</p>
                                    <div className={`max-h-[420px] overflow-auto rounded-[10px] border ${label === 'New version' ? 'border-accent ring-2 ring-accent/15' : 'border-line'}`}>
                                        <WebsiteRenderer data={preview(content as Section['content'])} extraAssets={extraAssets} showHeader={false} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        !error && (
                            <div className="rounded-[10px] border border-line">
                                <p className="border-b border-line px-3 py-2 text-xs font-semibold tracking-wide text-muted uppercase">Current</p>
                                <div className="max-h-[320px] overflow-auto">
                                    <WebsiteRenderer data={preview(section.content)} extraAssets={extraAssets} showHeader={false} />
                                </div>
                            </div>
                        )
                    )}
                </div>
            )}
        </Modal>
    );
}
