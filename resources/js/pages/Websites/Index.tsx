import { Plus, SearchX, Sparkles } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { LinkButton } from '@/components/ui/Button';
import { PageHeader } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/States';
import { WebsiteCard } from '@/components/dashboard/WebsiteCard';
import { WebsiteFilters } from '@/components/dashboard/WebsiteFilters';
import { Pagination } from '@/components/Pagination';
import { WebsitesNav } from '@/components/dashboard/WebsitesNav';
import type { Paginated, WebsiteCardData } from '@/types';

export default function WebsitesIndex({ websites, filters }: { websites: Paginated<WebsiteCardData>; filters: { search: string; status: string } }) {
    const filtering = filters.search !== '' || filters.status !== 'all';
    return (
        <DashboardLayout title="My Websites">
            <PageHeader
                title="My Websites"
                description="All websites across your businesses."
                actions={
                    <LinkButton href="/websites/create" icon={<Plus className="h-4 w-4" />}>
                        Create website
                    </LinkButton>
                }
            />
            <WebsitesNav active="websites" />
            <div className="mb-6">
                <WebsiteFilters path="/websites" filters={filters} only={['websites', 'filters']} />
            </div>
            {websites.data.length === 0 ? (
                filtering ? (
                    <EmptyState icon={<SearchX className="h-5 w-5" />} title="No websites match your filters" description="Try a different search or status." />
                ) : (
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
                )
            ) : (
                <>
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        {websites.data.map((w) => (
                            <WebsiteCard key={w.id} website={w} />
                        ))}
                    </div>
                    <Pagination page={websites} />
                </>
            )}
        </DashboardLayout>
    );
}
