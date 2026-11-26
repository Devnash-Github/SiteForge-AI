import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Tabs } from '@/components/ui/Tabs';

export function WebsiteFilters({ path, filters, only }: { path: string; filters: { search: string; status: string }; only: string[] }) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => {
            router.get(path, { ...(search ? { search } : {}), ...(status !== 'all' ? { status } : {}) }, { preserveState: true, preserveScroll: true, replace: true, only });
        }, 300);
        return () => clearTimeout(t);
    }, [search, status]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search by name or city"
                    aria-label="Search websites"
                    className="h-9 w-64 rounded-[10px] border border-line bg-white pr-3 pl-9 text-sm shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25 focus:outline-none"
                />
            </div>
            <Tabs
                value={status}
                onChange={setStatus}
                items={[
                    { value: 'all', label: 'All' },
                    { value: 'published', label: 'Published' },
                    { value: 'draft', label: 'Drafts' },
                ]}
            />
        </div>
    );
}
