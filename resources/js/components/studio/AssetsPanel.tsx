import { useState } from 'react';
import { ImagePlus, LayoutGrid, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { FileUploader } from '@/components/ui/FileUploader';
import { ConfirmDialog } from '@/components/ui/Modal';
import { Tooltip } from '@/components/ui/Tooltip';
import { useToast } from '@/components/ui/Toast';
import { errorMessage, http } from '@/lib/http';
import { formatBytes } from '@/lib/utils';
import type { MediaItem, Section } from '@/types';
import type { Studio } from './useStudio';

interface Props {
    studio: Studio;
    businessId: number;
    media: MediaItem[];
    setMedia: (m: MediaItem[]) => void;
    maxMb: number;
}

export function AssetsPanel({ studio, businessId, media, setMedia, maxMb }: Props) {
    const toast = useToast();
    const [files, setFiles] = useState<File[]>([]);
    const [progress, setProgress] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>();
    const [deleting, setDeleting] = useState<MediaItem | null>(null);

    const hero = studio.data.sections.find((s) => s.type === 'hero');
    const gallery = studio.data.sections.find((s) => s.type === 'gallery');

    const upload = async () => {
        if (!files.length) return;
        const body = new FormData();
        files.forEach((f) => body.append('files[]', f));
        setProgress(0);
        setError(undefined);
        try {
            const r = await http.post<{ media: MediaItem[] }>(`/businesses/${businessId}/media`, body, {
                onUploadProgress: (e) => setProgress(e.total ? (e.loaded / e.total) * 100 : 50),
            });
            setMedia([...media, ...r.data.media]);
            setFiles([]);
            toast(`${r.data.media.length} image${r.data.media.length === 1 ? '' : 's'} uploaded.`);
        } catch (e) {
            setError(errorMessage(e, 'Upload failed.'));
        } finally {
            setProgress(null);
        }
    };

    const remove = async (item: MediaItem) => {
        const ok = await studio.flush();
        if (!ok) return;
        try {
            await http.delete(`/businesses/${businessId}/media/${item.id}`);
            setMedia(media.filter((m) => m.id !== item.id));
            // Mirror the server: drop references from sections.
            studio.replaceLocal((d) => ({
                ...d,
                sections: d.sections.map((s) => {
                    const c = { ...s.content } as Record<string, unknown>;
                    if (c.image_id === item.id) c.image_id = null;
                    if (Array.isArray(c.image_ids)) c.image_ids = (c.image_ids as number[]).filter((x) => x !== item.id);
                    return { ...s, content: c } as unknown as Section;
                }),
            }));
            toast('Image deleted.');
        } catch (e) {
            toast(errorMessage(e), 'error');
        }
        setDeleting(null);
    };

    const useAsHero = (item: MediaItem) => hero && hero.type === 'hero' && studio.updateSection(hero.id, { ...hero.content, image_id: item.id });
    const addToGallery = (item: MediaItem) =>
        gallery && gallery.type === 'gallery' && !gallery.content.image_ids.includes(item.id) && studio.updateSection(gallery.id, { ...gallery.content, image_ids: [...gallery.content.image_ids, item.id] });

    return (
        <div className="h-full space-y-6 overflow-y-auto px-4 py-5">
            <div className="space-y-3">
                <FileUploader files={files} onChange={setFiles} accept={['image/jpeg', 'image/png', 'image/webp']} maxSizeMb={maxMb} progress={progress} error={error} compact />
                {files.length > 0 && progress === null && (
                    <Button className="w-full" onClick={upload} icon={<ImagePlus className="h-4 w-4" />}>
                        Upload {files.length} image{files.length === 1 ? '' : 's'}
                    </Button>
                )}
            </div>

            <div>
                <p className="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">Your images ({media.length})</p>
                {media.length === 0 ? (
                    <p className="text-sm text-muted">No images yet. Photos make a website feel real — upload a few of your business, team or products.</p>
                ) : (
                    <div className="grid grid-cols-2 gap-3">
                        {media.map((m) => (
                            <div key={m.id} className="group overflow-hidden rounded-[10px] border border-line bg-white">
                                <img src={m.url} alt={m.alt_text ?? ''} className="aspect-square w-full object-cover" loading="lazy" />
                                <div className="flex items-center justify-between gap-1 px-2 py-1.5">
                                    <span className="truncate text-[11px] text-muted" title={m.file_name}>
                                        {formatBytes(m.size)}
                                    </span>
                                    <div className="flex">
                                        {hero && (
                                            <Tooltip label="Use as hero image">
                                                <button onClick={() => useAsHero(m)} className="rounded p-1 text-muted hover:bg-gray-100 hover:text-ink" aria-label="Use as hero image">
                                                    <ImagePlus className="h-3.5 w-3.5" />
                                                </button>
                                            </Tooltip>
                                        )}
                                        {gallery && (
                                            <Tooltip label="Add to gallery">
                                                <button onClick={() => addToGallery(m)} className="rounded p-1 text-muted hover:bg-gray-100 hover:text-ink" aria-label="Add to gallery">
                                                    <LayoutGrid className="h-3.5 w-3.5" />
                                                </button>
                                            </Tooltip>
                                        )}
                                        <Tooltip label="Delete">
                                            <button onClick={() => setDeleting(m)} className="rounded p-1 text-muted hover:bg-red-50 hover:text-red-600" aria-label="Delete image">
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </Tooltip>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={!!deleting}
                danger
                title="Delete this image?"
                message="The image is deleted and removed from every section that uses it, including your published site."
                confirmLabel="Delete image"
                onCancel={() => setDeleting(null)}
                onConfirm={() => deleting && remove(deleting)}
            />
        </div>
    );
}
