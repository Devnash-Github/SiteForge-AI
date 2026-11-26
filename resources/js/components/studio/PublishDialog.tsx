import { useEffect, useState } from 'react';
import { CheckCircle2, Copy, ExternalLink, Globe, Loader2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Modal, ConfirmDialog } from '@/components/ui/Modal';
import { Notice } from '@/components/ui/States';
import { useToast } from '@/components/ui/Toast';
import { errorMessage, fieldErrors, http } from '@/lib/http';
import type { StudioWebsite } from '@/types';
import type { Studio } from './useStudio';

interface Props {
    open: boolean;
    onClose: () => void;
    website: StudioWebsite;
    studio: Studio;
    onPublished: (w: Partial<StudioWebsite>) => void;
}

type SlugCheck = { valid: boolean; available: boolean; url: string | null } | null;

export function PublishDialog({ open, onClose, website, studio, onPublished }: Props) {
    const toast = useToast();
    const [slug, setSlug] = useState(website.slug);
    const [check, setCheck] = useState<SlugCheck>(null);
    const [checking, setChecking] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [confirmUnpublish, setConfirmUnpublish] = useState(false);
    const [wasOpen, setWasOpen] = useState(open);

    if (open !== wasOpen) {
        setWasOpen(open);
        if (open) {
            setSlug(website.slug);
            setSuccess(null);
            setError(null);
        }
    }

    useEffect(() => {
        if (!open) return;
        if (slug === website.slug) {
            setCheck({ valid: true, available: true, url: website.public_url });
            return;
        }
        setChecking(true);
        const t = setTimeout(async () => {
            try {
                const r = await http.get(`/websites/${website.id}/slug-check`, { params: { slug } });
                setCheck(r.data);
            } catch {
                setCheck(null);
            } finally {
                setChecking(false);
            }
        }, 350);
        return () => clearTimeout(t);
    }, [slug, open, website.id, website.slug, website.public_url]);

    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    const publish = async () => {
        setBusy(true);
        setError(null);
        try {
            const saved = await studio.flush();
            if (!saved) throw new Error('unsaved');
            const r = await http.post(`/websites/${website.id}/publish`, { slug });
            setSuccess(r.data.url);
            studio.setUnpublished(false);
            onPublished({ status: 'published', is_published: true, slug: r.data.slug, public_url: r.data.url, published_at: r.data.published_at, has_unpublished_changes: false });
        } catch (e) {
            const fe = fieldErrors(e);
            setError(fe.slug ?? fe.website ?? (e instanceof Error && e.message === 'unsaved' ? 'Some changes could not be saved. Fix them before publishing.' : errorMessage(e, 'Publishing failed.')));
        } finally {
            setBusy(false);
        }
    };

    const unpublish = async () => {
        setBusy(true);
        try {
            await http.post(`/websites/${website.id}/unpublish`);
            onPublished({ status: 'unpublished', is_published: false, has_unpublished_changes: false });
            toast('Website unpublished. It is no longer publicly visible.');
            setConfirmUnpublish(false);
            onClose();
        } catch (e) {
            toast(errorMessage(e), 'error');
        } finally {
            setBusy(false);
        }
    };

    const copy = async (url: string) => {
        try {
            await navigator.clipboard.writeText(url);
            toast('Link copied.');
        } catch {
            toast('Could not copy — select the link and copy it manually.', 'error');
        }
    };

    if (success) {
        return (
            <Modal open={open} onClose={onClose} size="md" footer={<Button onClick={onClose}>Close</Button>}>
                <div className="py-2 text-center">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50">
                        <CheckCircle2 className="h-6 w-6 text-emerald-600" />
                    </div>
                    <h2 className="text-lg font-semibold text-ink">Published successfully</h2>
                    <p className="mt-1 text-sm text-muted">Your website is live at:</p>
                    <div className="mt-4 flex items-center gap-2 rounded-[10px] border border-line bg-gray-50 p-1.5 pl-3">
                        <span className="min-w-0 flex-1 truncate text-left font-mono text-sm text-ink">{success}</span>
                        <Button size="sm" variant="secondary" icon={<Copy className="h-3.5 w-3.5" />} onClick={() => copy(success)}>
                            Copy link
                        </Button>
                    </div>
                    <a href={success} target="_blank" rel="noopener noreferrer" className="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-accent hover:underline">
                        View website <ExternalLink className="h-3.5 w-3.5" />
                    </a>
                </div>
            </Modal>
        );
    }

    const slugOk = check?.valid && check.available;

    return (
        <>
            <Modal
                open={open}
                onClose={onClose}
                title={website.is_published ? 'Manage publishing' : 'Publish website'}
                description={website.is_published ? 'Your website is live. Publish again to push your latest changes.' : 'Choose the address for your website. Anyone with the link can view it.'}
                footer={
                    <>
                        {website.is_published && (
                            <Button variant="ghost" className="mr-auto hover:!text-red-600" onClick={() => setConfirmUnpublish(true)} disabled={busy}>
                                Unpublish
                            </Button>
                        )}
                        <Button variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button onClick={publish} loading={busy} disabled={!slugOk || checking} icon={<Globe className="h-4 w-4" />}>
                            {website.is_published ? 'Update published site' : 'Publish'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    {website.is_published && (
                        <Notice tone={studio.unpublished ? 'warning' : 'success'} icon={studio.unpublished ? undefined : <CheckCircle2 className="h-4 w-4" />}>
                            {studio.unpublished ? 'You have changes that are not live yet.' : 'The live site is up to date.'}{' '}
                            <a href={website.public_url} target="_blank" rel="noopener noreferrer" className="font-medium underline">
                                Open live site
                            </a>
                        </Notice>
                    )}
                    <div>
                        <label htmlFor="slug" className="mb-1.5 block text-[13px] font-medium text-ink">
                            Website address
                        </label>
                        <div className={`flex items-center overflow-hidden rounded-[10px] border shadow-sm focus-within:ring-2 focus-within:ring-accent/25 ${check && !slugOk && !checking ? 'border-red-400' : 'border-line focus-within:border-accent'}`}>
                            <span className="border-r border-line bg-gray-50 px-3 py-2.5 text-sm whitespace-nowrap text-muted">{origin}/sites/</span>
                            <input
                                id="slug"
                                value={slug}
                                onChange={(e) => setSlug(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-{2,}/g, '-').slice(0, 80))}
                                className="h-10 min-w-0 flex-1 px-3 text-sm focus:outline-none"
                            />
                            <span className="pr-3">
                                {checking ? <Loader2 className="h-4 w-4 animate-spin text-muted" /> : slugOk ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : check ? <XCircle className="h-4 w-4 text-red-500" /> : null}
                            </span>
                        </div>
                        <p className={`mt-1.5 text-xs ${check && !slugOk && !checking ? 'text-red-600' : 'text-muted'}`}>
                            {checking ? 'Checking…' : !check ? 'Lowercase letters, numbers and hyphens.' : !check.valid ? 'Use 3–80 lowercase letters, numbers and single hyphens.' : !check.available ? 'This address is already taken.' : 'This address is available.'}
                        </p>
                    </div>
                    {error && <p className="rounded-[10px] bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
                </div>
            </Modal>
            <ConfirmDialog
                open={confirmUnpublish}
                danger
                title="Unpublish this website?"
                message="The public link will stop working. Your content stays here and you can publish again at any time."
                confirmLabel="Unpublish"
                loading={busy}
                onCancel={() => setConfirmUnpublish(false)}
                onConfirm={unpublish}
            />
        </>
    );
}
