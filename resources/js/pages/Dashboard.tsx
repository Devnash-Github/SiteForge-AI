import { Link, usePage } from '@inertiajs/react';
import { FileText, Globe, Plus, Rocket, SearchX, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { LinkButton } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/States';
import { WebsiteCard } from '@/components/dashboard/WebsiteCard';
import { WebsiteFilters } from '@/components/dashboard/WebsiteFilters';
import { greeting } from '@/lib/utils';
import type { SharedProps, WebsiteCardData } from '@/types';

interface Props {
    stats: { total: number; published: number; draft: number };
    websites: WebsiteCardData[];
    filters: { search: string; status: string };
}

function Stat({ label, value, icon }: { label: string; value: number; icon: ReactNode }) {
    return (
        <div className="flex items-center gap-4 rounded-[12px] border border-line bg-white p-5 shadow-card">
            <div className="flex h-10 w-10 items-center justify-center rounded-[10px] bg-accent-soft text-accent">{icon}</div>
            <div>
                <p className="text-[13px] text-muted">{label}</p>
                <p className="text-2xl font-semibold tracking-tight text-ink">{value}</p>
            </div>
        </div>
    );
}

export default function Dashboard({ stats, websites, filters }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const filtering = filters.search !== '' || (filters.status && filters.status !== 'all');

    return (
        <DashboardLayout title="Dashboard">
            <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        {greeting()}, {firstName}
                    </h1>
                    <p className="mt-1.5 text-sm text-muted">Create a website from the information you already have about a business.</p>
                </div>
                <LinkButton href="/websites/create" icon={<Plus className="h-4 w-4" />}>
                    Create website
                </LinkButton>
            </div>

            <div className="mb-10 grid gap-4 sm:grid-cols-3">
                <Stat label="Total websites" value={stats.total} icon={<Globe className="h-5 w-5" />} />
                <Stat label="Published" value={stats.published} icon={<Rocket className="h-5 w-5" />} />
                <Stat label="Drafts" value={stats.draft} icon={<FileText className="h-5 w-5" />} />
            </div>

            {stats.total === 0 ? (
                <EmptyState
                    icon={<Sparkles className="h-5 w-5" />}
                    title="Your websites will appear here."
                    description="Create your first website with AI and turn your business information into a professional online presence."
                    action={
                        <LinkButton href="/websites/create" icon={<Plus className="h-4 w-4" />}>
                            Create website
                        </LinkButton>
                    }
                />
            ) : (
                <>
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-[15px] font-semibold text-ink">Recent websites</h2>
                        <WebsiteFilters path="/dashboard" filters={filters} only={['websites', 'filters']} />
                    </div>
                    {websites.length === 0 ? (
                        <EmptyState icon={<SearchX className="h-5 w-5" />} title="No websites match your filters" description={filtering ? 'Try a different search or status.' : undefined} />
                    ) : (
                        <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            {websites.map((w) => (
                                <WebsiteCard key={w.id} website={w} />
                            ))}
                        </div>
                    )}
                    {stats.total > websites.length && !filtering && (
                        <div className="mt-6 text-center">
                            <Link href="/websites" className="text-sm font-medium text-accent hover:underline">
                                View all {stats.total} websites →
                            </Link>
                        </div>
                    )}
                </>
            )}
        </DashboardLayout>
    );
}
