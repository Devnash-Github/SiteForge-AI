import { useState, type FormEvent, type ReactNode } from 'react';
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FileText, Globe, Image as ImageIcon, Lock, MapPin, MessageSquareText, Plus, RotateCw, Search, Share2, Trash2, XCircle } from 'lucide-react';
import { Badge, SourceStatusBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Input, Textarea } from '@/components/ui/Field';
import { FileUploader } from '@/components/ui/FileUploader';
import { ConfirmDialog, Modal } from '@/components/ui/Modal';
import { Spinner } from '@/components/ui/States';
import { Tabs } from '@/components/ui/Tabs';
import type { SourceItem } from '@/types';

const ICONS: Record<string, ReactNode> = {
    text: <MessageSquareText className="h-4 w-4" />,
    pdf: <FileText className="h-4 w-4" />,
    website: <Globe className="h-4 w-4" />,
    social: <Share2 className="h-4 w-4" />,
    google_business: <MapPin className="h-4 w-4" />,
    image: <ImageIcon className="h-4 w-4" />,
    search_result: <Search className="h-4 w-4" />,
};

const TYPE_LABEL: Record<string, string> = {
    text: 'Text',
    pdf: 'PDF',
    website: 'Website',
    social: 'Social profile',
    google_business: 'Google Business',
    image: 'Photo',
    search_result: 'Web search',
};

function SourceRow({ source, businessId }: { source: SourceItem; businessId: number }) {
    const [confirmRemove, setConfirmRemove] = useState(false);
    const base = `/businesses/${businessId}/sources/${source.id}`;
    const busy = source.status === 'pending' || source.status === 'processing';
    const uncertain = source.identity_status === 'uncertain';
    const rejected = source.identity_status === 'rejected';

    return (
        <li className={`px-5 py-4 ${rejected ? 'opacity-55' : ''}`}>
            <div className="flex items-start gap-3">
                <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-muted">{ICONS[source.type] ?? <Globe className="h-4 w-4" />}</div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-medium text-ink" title={source.label}>
                            {source.label}
                        </p>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                        <span>{TYPE_LABEL[source.type] ?? source.type}</span>
                        {source.url && (
                            <a href={source.url} target="_blank" rel="noopener noreferrer" className="max-w-[180px] truncate hover:text-accent hover:underline">
                                {source.url.replace(/^https?:\/\/(www\.)?/, '')}
                            </a>
                        )}
                        {source.status === 'completed' && source.facts_found != null && <span>· {source.facts_found} facts</span>}
                    </div>
                </div>
                <div className="flex shrink-0 items-center gap-1.5">
                    {busy && <Spinner className="h-3.5 w-3.5" />}
                    {rejected ? <Badge>Not my business</Badge> : <SourceStatusBadge status={source.status} />}
                </div>
            </div>

            {source.error_message && (
                <p className={`mt-2.5 flex items-start gap-1.5 text-xs ${source.status === 'blocked' ? 'text-amber-800' : 'text-red-700'}`}>
                    {source.status === 'blocked' ? <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0" /> : <XCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />}
                    {source.error_message}
                </p>
            )}
            {source.ai_error && source.status === 'completed' && <p className="mt-2 text-xs text-amber-800">AI reading skipped: {source.ai_error} Basic extraction was used.</p>}
            {source.page_error && <p className="mt-2 text-xs text-muted">Page not readable ({source.page_error}); only the search snippet was used.</p>}

            {uncertain && (
                <div className="mt-3 rounded-[10px] border border-amber-200 bg-amber-50 p-3">
                    <p className="flex items-center gap-1.5 text-xs font-medium text-amber-900">
                        <AlertTriangle className="h-3.5 w-3.5" /> Is this your business?
                    </p>
                    {source.snippet && <p className="mt-1.5 line-clamp-3 text-xs text-amber-900/80">{source.snippet}</p>}
                    {source.match_reasons.length > 0 && <p className="mt-1.5 text-[11px] text-amber-900/70">Matched on: {source.match_reasons.join(', ')}</p>}
                    <div className="mt-2.5 flex gap-2">
                        <Button size="sm" variant="secondary" icon={<CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />} onClick={() => router.post(`${base}/identity`, { decision: 'confirm' }, { preserveScroll: true })}>
                            Yes, it’s mine
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => router.post(`${base}/identity`, { decision: 'reject' }, { preserveScroll: true })}>
                            No, different business
                        </Button>
                    </div>
                </div>
            )}

            {!busy && (
                <div className="mt-2.5 flex gap-1">
                    {(source.status === 'failed' || source.status === 'blocked') && source.type !== 'image' && !uncertain && (
                        <Button size="sm" variant="ghost" icon={<RotateCw className="h-3.5 w-3.5" />} onClick={() => router.post(`${base}/retry`, {}, { preserveScroll: true })}>
                            Retry
                        </Button>
                    )}
                    <Button size="sm" variant="ghost" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => setConfirmRemove(true)}>
                        Remove
                    </Button>
                </div>
            )}

            <ConfirmDialog
                open={confirmRemove}
                title="Remove this source?"
                message="Facts you haven't reviewed from this source are removed too. Facts you confirmed or rejected are kept."
                confirmLabel="Remove"
                danger
                onCancel={() => setConfirmRemove(false)}
                onConfirm={() => router.delete(base, { preserveScroll: true, onSuccess: () => setConfirmRemove(false) })}
            />
        </li>
    );
}

function AddSourceDialog({ open, onClose, businessId, maxMb }: { open: boolean; onClose: () => void; businessId: number; maxMb: number }) {
    const [mode, setMode] = useState<'url' | 'text' | 'files'>('url');
    const form = useForm<{ url: string; text: string; files: File[] }>({ url: '', text: '', files: [] });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const payload = { url: mode === 'url' ? form.data.url : '', text: mode === 'text' ? form.data.text : '', files: mode === 'files' ? form.data.files : [] };
        form.transform(() => payload);
        form.post(`/businesses/${businessId}/sources`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const errors = form.errors as Record<string, string>;
    const fileError = errors.files ?? Object.entries(errors).find(([k]) => k.startsWith('files.'))?.[1];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Add a source"
            description="Add another link, some text, or files. We'll read it and suggest new facts."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={submit} loading={form.processing}>
                        Add source
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <Tabs
                    value={mode}
                    onChange={setMode}
                    items={[
                        { value: 'url', label: 'Link' },
                        { value: 'text', label: 'Text' },
                        { value: 'files', label: 'Files' },
                    ]}
                />
                {mode === 'url' && <Input label="Web address" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} error={errors.url} placeholder="https://…" autoFocus />}
                {mode === 'text' && (
                    <Textarea
                        label="Information"
                        value={form.data.text}
                        onChange={(e) => form.setData('text', e.target.value)}
                        error={errors.text}
                        rows={6}
                        placeholder="Paste a description, a price list, opening hours…"
                        hint="Text you add is treated as your own confirmed information."
                    />
                )}
                {mode === 'files' && <FileUploader files={form.data.files} onChange={(f) => form.setData('files', f)} maxSizeMb={maxMb} progress={form.progress?.percentage ?? null} error={fileError} compact />}
            </form>
        </Modal>
    );
}

export function SourcesPanel({ sources, businessId, maxMb }: { sources: SourceItem[]; businessId: number; maxMb: number }) {
    const [adding, setAdding] = useState(false);
    const done = sources.filter((s) => !['pending', 'processing'].includes(s.status)).length;

    return (
        <Card padded={false}>
            <div className="px-5 pt-5">
                <CardHeader
                    title="Sources"
                    description={sources.length ? `${done} of ${sources.length} processed` : 'Nothing added yet'}
                    action={
                        <Button size="sm" variant="secondary" icon={<Plus className="h-3.5 w-3.5" />} onClick={() => setAdding(true)}>
                            Add
                        </Button>
                    }
                    className="mb-3"
                />
            </div>
            {sources.length === 0 ? (
                <p className="px-5 pb-5 text-sm text-muted">Add a link, text or a PDF to collect more information.</p>
            ) : (
                <ul className="divide-y divide-line border-t border-line">
                    {sources.map((s) => (
                        <SourceRow key={s.id} source={s} businessId={businessId} />
                    ))}
                </ul>
            )}
            <AddSourceDialog open={adding} onClose={() => setAdding(false)} businessId={businessId} maxMb={maxMb} />
        </Card>
    );
}
