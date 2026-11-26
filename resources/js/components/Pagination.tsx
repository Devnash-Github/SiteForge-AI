import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) return null;
    return (
        <nav className="mt-8 flex flex-wrap items-center justify-center gap-1" aria-label="Pagination">
            {page.links.map((link, i) => {
                const label = link.label.replace('&laquo;', '‹').replace('&raquo;', '›').replace(/<[^>]*>/g, '');
                return link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        className={cn('min-w-8 rounded-lg px-2.5 py-1.5 text-center text-sm', link.active ? 'bg-accent text-white' : 'text-muted hover:bg-gray-100 hover:text-ink')}
                    >
                        {label}
                    </Link>
                ) : (
                    <span key={i} className="min-w-8 px-2.5 py-1.5 text-center text-sm text-gray-300">
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}
