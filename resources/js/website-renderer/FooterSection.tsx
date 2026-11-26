import type { ContactContent, FooterContent } from '@/types';

export function FooterSection({ content, nav, contact }: { content: FooterContent; nav: { id: string; label: string }[]; contact?: ContactContent }) {
    return (
        <footer style={{ background: 'var(--site-text)', color: 'var(--site-bg)' }}>
            <div className="mx-auto max-w-6xl px-6 py-14 @3xl:px-10">
                <div className="flex flex-col gap-10 @3xl:flex-row @3xl:justify-between">
                    <div className="max-w-sm">
                        <p className="text-lg font-semibold" style={{ fontFamily: 'var(--site-heading-font)' }}>
                            {content.business_name}
                        </p>
                        {content.tagline && <p className="mt-2 text-sm opacity-65">{content.tagline}</p>}
                    </div>
                    {nav.length > 0 && (
                        <nav aria-label="Footer" className="flex flex-wrap gap-x-6 gap-y-2 text-sm opacity-80">
                            {nav.map((n) => (
                                <a key={n.id} href={`#${n.id}`} className="hover:opacity-100 hover:underline">
                                    {n.label}
                                </a>
                            ))}
                        </nav>
                    )}
                    {content.show_contact && contact && (contact.phone || contact.email || contact.address) && (
                        <div className="space-y-1 text-sm opacity-80">
                            {contact.phone && <p>{contact.phone}</p>}
                            {contact.email && <p>{contact.email}</p>}
                            {contact.address && <p className="max-w-xs whitespace-pre-line">{contact.address}</p>}
                        </div>
                    )}
                </div>
                <div className="mt-12 border-t border-current/15 pt-6 text-xs opacity-50">
                    © {new Date().getFullYear()} {content.business_name}
                </div>
            </div>
        </footer>
    );
}
