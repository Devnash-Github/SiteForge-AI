import { useState } from 'react';
import { ArrowDown, ArrowLeft, ArrowUp, Eye, EyeOff, LayoutTemplate, Plus, Sparkles, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/Modal';
import { Tooltip } from '@/components/ui/Tooltip';
import type { MediaItem, Section } from '@/types';
import { SectionEditor } from './SectionEditors';
import type { Studio } from './useStudio';

const LABELS: Record<string, string> = { hero: 'Hero', about: 'About', services: 'Services', gallery: 'Gallery', contact: 'Contact', footer: 'Footer' };

export function sectionSummary(s: Section): string {
    switch (s.type) {
        case 'hero':
            return s.content.headline;
        case 'about':
            return s.content.heading;
        case 'services':
            return `${s.content.items.length} item${s.content.items.length === 1 ? '' : 's'}`;
        case 'gallery':
            return `${s.content.image_ids.length} photo${s.content.image_ids.length === 1 ? '' : 's'}`;
        case 'contact':
            return [s.content.phone, s.content.email].filter(Boolean).join(' · ') || 'No contact details';
        case 'footer':
            return s.content.business_name;
    }
}

interface Props {
    studio: Studio;
    selectedId: number | null;
    onSelect: (id: number | null) => void;
    sectionTypes: { type: string; label: string }[];
    media: MediaItem[];
    openAssets: () => void;
    onRegenerate: (section: Section) => void;
    aiAvailable: boolean;
}

export function SectionsPanel({ studio, selectedId, onSelect, sectionTypes, media, openAssets, onRegenerate, aiAvailable }: Props) {
    const [removing, setRemoving] = useState<Section | null>(null);
    const sections = studio.data.sections;
    const selected = sections.find((s) => s.id === selectedId) ?? null;
    const available = sectionTypes.filter((t) => !sections.some((s) => s.type === t.type));

    if (selected) {
        return (
            <div className="flex h-full flex-col">
                <div className="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <button onClick={() => onSelect(null)} className="flex items-center gap-1.5 text-[13px] font-medium text-muted hover:text-ink">
                        <ArrowLeft className="h-4 w-4" /> All sections
                    </button>
                    <span className="text-sm font-semibold text-ink">{LABELS[selected.type]}</span>
                </div>
                <div className="flex-1 overflow-y-auto px-4 py-5">
                    {aiAvailable && (
                        <Button variant="subtle" size="sm" className="mb-5 w-full" icon={<Sparkles className="h-3.5 w-3.5" />} onClick={() => onRegenerate(selected)}>
                            Rewrite with AI
                        </Button>
                    )}
                    <SectionEditor
                        key={selected.id}
                        section={selected}
                        onChange={(content) => studio.updateSection(selected.id, content)}
                        errors={studio.sectionErrors[selected.id] ?? {}}
                        media={media}
                        openAssets={openAssets}
                    />
                </div>
                <div className="flex items-center justify-between border-t border-line px-4 py-3">
                    <Button size="sm" variant="ghost" icon={selected.visible ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />} onClick={() => studio.setVisible(selected.id, !selected.visible)}>
                        {selected.visible ? 'Hide section' : 'Show section'}
                    </Button>
                    <Button size="sm" variant="ghost" className="hover:!text-red-600" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => setRemoving(selected)}>
                        Remove
                    </Button>
                </div>
                <ConfirmDialog
                    open={!!removing}
                    danger
                    title={`Remove the ${removing ? LABELS[removing.type] : ''} section?`}
                    message="Its content is deleted. You can add the section again later, but it will start empty."
                    confirmLabel="Remove section"
                    onCancel={() => setRemoving(null)}
                    onConfirm={async () => {
                        if (removing && (await studio.removeSection(removing.id))) onSelect(null);
                        setRemoving(null);
                    }}
                />
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col overflow-y-auto">
            <div className="px-4 py-4">
                <p className="text-xs text-muted">Click a section to edit it, or select it on the page.</p>
            </div>
            <ul className="space-y-1.5 px-3">
                {sections.map((s, i) => (
                    <li key={s.id} className={`group flex items-center gap-2 rounded-[10px] border bg-white px-3 py-2.5 transition-colors ${s.visible ? 'border-line' : 'border-dashed border-gray-300 bg-gray-50'} hover:border-accent/50`}>
                        <button onClick={() => onSelect(s.id)} className="flex min-w-0 flex-1 items-center gap-2.5 text-left">
                            <LayoutTemplate className={`h-4 w-4 shrink-0 ${s.visible ? 'text-accent' : 'text-gray-400'}`} />
                            <span className="min-w-0">
                                <span className={`block text-sm font-medium ${s.visible ? 'text-ink' : 'text-muted'}`}>{LABELS[s.type]}</span>
                                <span className="block truncate text-xs text-muted">{s.visible ? sectionSummary(s) : 'Hidden'}</span>
                            </span>
                        </button>
                        <div className="flex shrink-0 items-center opacity-70 group-hover:opacity-100">
                            <Tooltip label="Move up">
                                <button onClick={() => studio.move(s.id, -1)} disabled={i === 0} className="rounded-md p-1 text-muted hover:bg-gray-100 hover:text-ink disabled:opacity-25" aria-label="Move up">
                                    <ArrowUp className="h-3.5 w-3.5" />
                                </button>
                            </Tooltip>
                            <Tooltip label="Move down">
                                <button onClick={() => studio.move(s.id, 1)} disabled={i === sections.length - 1} className="rounded-md p-1 text-muted hover:bg-gray-100 hover:text-ink disabled:opacity-25" aria-label="Move down">
                                    <ArrowDown className="h-3.5 w-3.5" />
                                </button>
                            </Tooltip>
                            <Tooltip label={s.visible ? 'Hide' : 'Show'}>
                                <button onClick={() => studio.setVisible(s.id, !s.visible)} className="rounded-md p-1 text-muted hover:bg-gray-100 hover:text-ink" aria-label={s.visible ? 'Hide section' : 'Show section'}>
                                    {s.visible ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}
                                </button>
                            </Tooltip>
                        </div>
                    </li>
                ))}
            </ul>
            {available.length > 0 && (
                <div className="mt-5 border-t border-line px-4 pt-4 pb-6">
                    <p className="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">Add section</p>
                    <div className="flex flex-wrap gap-2">
                        {available.map((t) => (
                            <Button key={t.type} size="sm" variant="secondary" icon={<Plus className="h-3.5 w-3.5" />} onClick={async () => { await studio.addSection(t.type); }}>
                                {t.label}
                            </Button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
