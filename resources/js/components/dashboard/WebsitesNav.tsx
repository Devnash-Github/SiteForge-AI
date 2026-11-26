import { Link } from '@inertiajs/react';

export function WebsitesNav({ active }: { active: 'websites' | 'businesses' }) {
    const item = (href: string, label: string, key: string) => (
        <Link
            href={href}
            className={`-mb-px border-b-2 pb-2.5 text-sm font-medium ${active === key ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink'}`}
        >
            {label}
        </Link>
    );
    return (
        <div className="mb-6 flex gap-6 border-b border-line">
            {item('/websites', 'Websites', 'websites')}
            {item('/businesses', 'Businesses', 'businesses')}
        </div>
    );
}
