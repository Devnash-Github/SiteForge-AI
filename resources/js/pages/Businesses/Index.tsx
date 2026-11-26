import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Building2, ClipboardCheck, Plus, Trash2 } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { LinkButton } from '@/components/ui/Button';
import { PageHeader } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/Modal';
import { EmptyState } from '@/components/ui/States';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/Pagination';
import { formatRelative } from '@/lib/utils';
import type { BusinessSummary, Paginated } from '@/types';
import { WebsitesNav } from '@/components/dashboard/WebsitesNav';

type Row = BusinessSummary & { websites_count: number; sources_count: number; media_count: number; confirmed_facts_count: number };

export default function BusinessesIndex({ businesses }: { businesses: Paginated<Row> }) {
    const [deleting, setDeleting] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);

    const destroy = () => {
        if (!deleting) return;
        setBusy(true);
        router.delete(`/businesses/${deleting.id}`, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setDeleting(null) });
    };

    return (
        <DashboardLayout title="Businesses">
            <PageHeader
                title="My Websites"
                description="Businesses hold the information your websites are built from."
                actions={
                    <LinkButton href="/websites/create" icon={<Plus className="h-4 w-4" />}>
                        Add business
                    </LinkButton>
                }
            />
            <WebsitesNav active="businesses" />

            {businesses.data.length === 0 ? (
                <EmptyState icon={<Building2 className="h-5 w-5" />} title="No businesses yet" description="Add a business to start building its website." action={<LinkButton href="/websites/create">Add business</LinkButton>} />
            ) : (
                <div className="overflow-hidden rounded-[12px] border border-line bg-white shadow-card">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-line bg-gray-50/70 text-xs font-medium text-muted">
                            <tr>
                                <th className="px-5 py-3">Business</th>
                                <th className="hidden px-5 py-3 md:table-cell">Information</th>
                                <th className="hidden px-5 py-3 sm:table-cell">Websites</th>
                                <th className="hidden px-5 py-3 lg:table-cell">Updated</th>
                                <th className="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {businesses.data.map((b) => (
                                <tr key={b.id} className="hover:bg-gray-50/60">
                                    <td className="px-5 py-3.5">
                                        <Link href={`/businesses/${b.id}`} className="font-medium text-ink hover:text-accent">
                                            {b.name}
                                        </Link>
                                        <p className="text-xs text-muted">
                                            {b.city}
                                            {b.category ? ` · ${b.category}` : ''}
                                        </p>
                                    </td>
                                    <td className="hidden px-5 py-3.5 text-muted md:table-cell">
                                        {b.confirmed_facts_count} confirmed facts · {b.sources_count} sources · {b.media_count} photos
                                        {b.status === 'researching' && (
                                            <Badge tone="blue" className="ml-2">
                                                Processing
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="hidden px-5 py-3.5 text-muted sm:table-cell">{b.websites_count}</td>
                                    <td className="hidden px-5 py-3.5 text-muted lg:table-cell">{formatRelative(b.updated_at)}</td>
                                    <td className="px-5 py-3.5">
                                        <div className="flex justify-end gap-1">
                                            <LinkButton href={`/businesses/${b.id}/research`} variant="ghost" size="sm" icon={<ClipboardCheck className="h-4 w-4" />}>
                                                Review info
                                            </LinkButton>
                                            <button onClick={() => setDeleting(b)} className="rounded-lg p-2 text-muted hover:bg-red-50 hover:text-red-600" aria-label={`Delete ${b.name}`}>
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination page={businesses} />

            <ConfirmDialog
                open={!!deleting}
                danger
                title={`Delete ${deleting?.name ?? 'business'}?`}
                message="This permanently deletes the business, its uploaded files, collected information and all of its websites (including published ones)."
                confirmLabel="Delete business"
                loading={busy}
                onConfirm={destroy}
                onCancel={() => setDeleting(null)}
            />
        </DashboardLayout>
    );
}
