import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ExternalLink, Eye, PencilLine, Rocket, Trash2 } from 'lucide-react';
import type { WebsiteCardData } from '@/types';
import { WebsiteStatusBadge } from '@/components/ui/Badge';
import { ConfirmDialog } from '@/components/ui/Modal';
import { Tooltip } from '@/components/ui/Tooltip';
import { formatRelative } from '@/lib/utils';
import { WebsiteThumbnail } from './WebsiteThumbnail';

export function WebsiteCard({ website }: { website: WebsiteCardData }) {
    const [confirming, setConfirming] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const generating = website.generation_status === 'pending' || website.generation_status === 'processing';
    const hasContent = website.section_count > 0;
    const editHref = hasContent || generating ? `/websites/${website.id}/studio` : `/businesses/${website.business.id}/research`;

    const destroy = () => {
        setDeleting(true);
        router.delete(`/websites/${website.id}`, { preserveScroll: true, onFinish: () => setDeleting(false), onSuccess: () => setConfirming(false) });
    };

    const iconBtn = 'flex h-8 w-8 items-center justify-center rounded-lg text-muted transition-colors hover:bg-gray-100 hover:text-ink';

    return (
        <div className="group overflow-hidden rounded-[12px] border border-line bg-white shadow-card transition-shadow hover:shadow-pop">
            <Link href={editHref} className="block border-b border-line">
                <WebsiteThumbnail data={website.thumbnail} generating={generating} />
            </Link>
            <div className="p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <Link href={editHref} className="block truncate text-[15px] font-semibold text-ink hover:text-accent">
                            {website.business.name}
                        </Link>
                        <p className="truncate text-[13px] text-muted">{website.business.city}</p>
                    </div>
                    <WebsiteStatusBadge status={website.status} />
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <span className="text-xs text-muted">
                        {generating ? 'Generating…' : `Updated ${formatRelative(website.updated_at)}`}
                        {website.has_unpublished_changes && <span className="ml-1.5 text-amber-700">· unpublished changes</span>}
                    </span>
                    <div className="flex items-center">
                        <Tooltip label={hasContent ? 'Edit' : 'Review info'}>
                            <Link href={editHref} className={iconBtn} aria-label="Edit">
                                <PencilLine className="h-4 w-4" />
                            </Link>
                        </Tooltip>
                        {hasContent && (
                            <Tooltip label="Preview">
                                <a href={`/websites/${website.id}/preview`} target="_blank" rel="noopener" className={iconBtn} aria-label="Preview">
                                    <Eye className="h-4 w-4" />
                                </a>
                            </Tooltip>
                        )}
                        {hasContent && (
                            <Tooltip label={website.status === 'published' ? 'Manage publishing' : 'Publish'}>
                                <Link href={`/websites/${website.id}/studio?publish=1`} className={iconBtn} aria-label="Publish">
                                    <Rocket className="h-4 w-4" />
                                </Link>
                            </Tooltip>
                        )}
                        {website.public_url && (
                            <Tooltip label="Open live site">
                                <a href={website.public_url} target="_blank" rel="noopener noreferrer" className={iconBtn} aria-label="Open live site">
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            </Tooltip>
                        )}
                        <Tooltip label="Delete">
                            <button onClick={() => setConfirming(true)} className={`${iconBtn} hover:!bg-red-50 hover:!text-red-600`} aria-label="Delete">
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </Tooltip>
                    </div>
                </div>
            </div>
            <ConfirmDialog
                open={confirming}
                danger
                title="Delete this website?"
                message={
                    <>
                        “{website.name}” will be removed{website.status === 'published' ? ' and its public page will stop working' : ''}. The business and its information are kept. This cannot be undone.
                    </>
                }
                confirmLabel="Delete website"
                loading={deleting}
                onConfirm={destroy}
                onCancel={() => setConfirming(false)}
            />
        </div>
    );
}
