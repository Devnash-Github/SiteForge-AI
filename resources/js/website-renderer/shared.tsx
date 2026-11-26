import type { AnchorHTMLAttributes, ReactNode } from 'react';
import type { Asset } from '@/types';

export type AssetMap = Record<number, Asset>;

export function assetFor(assets: AssetMap, id: number | null | undefined): Asset | null {
    if (id == null) return null;
    return assets[id] ?? null;
}

/** Only allow link targets the schema allows; anything else becomes a harmless anchor. */
export function safeHref(url: string | undefined): string {
    if (!url) return '#contact';
    if (/^(https?:\/\/|mailto:|tel:|#[a-z0-9-]+$)/i.test(url)) return url;
    return '#contact';
}

export function SiteButton({ href, children, variant = 'solid', ...rest }: { href: string; children: ReactNode; variant?: 'solid' | 'outline' } & AnchorHTMLAttributes<HTMLAnchorElement>) {
    const external = /^https?:\/\//i.test(href);
    return (
        <a
            href={safeHref(href)}
            {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
            className="inline-flex items-center justify-center px-6 py-3 text-[15px] font-semibold transition-opacity hover:opacity-90"
            style={
                variant === 'solid'
                    ? { background: 'var(--site-primary)', color: 'var(--site-on-primary)', borderRadius: 'var(--site-radius)' }
                    : { border: '1.5px solid var(--site-primary)', color: 'var(--site-primary)', borderRadius: 'var(--site-radius)' }
            }
            {...rest}
        >
            {children}
        </a>
    );
}

export function SectionHeading({ children, center }: { children: ReactNode; center?: boolean }) {
    return (
        <h2 className={`text-[28px] leading-tight font-semibold tracking-tight @3xl:text-[36px] ${center ? 'text-center' : ''}`} style={{ fontFamily: 'var(--site-heading-font)' }}>
            {children}
        </h2>
    );
}

export function Paragraphs({ text, className }: { text: string; className?: string }) {
    return (
        <>
            {text
                .split(/\n{2,}/)
                .filter(Boolean)
                .map((p, i) => (
                    <p key={i} className={className}>
                        {p}
                    </p>
                ))}
        </>
    );
}

export function Placeholder({ children }: { children: ReactNode }) {
    return <p className="rounded-lg border border-dashed border-current/20 px-4 py-6 text-center text-sm opacity-60">{children}</p>;
}
