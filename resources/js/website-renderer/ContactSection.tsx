import { Clock, Globe, Mail, MapPin, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import type { ContactContent } from '@/types';
import { Placeholder, SectionHeading, SiteButton } from './shared';

function Row({ icon, label, children }: { icon: ReactNode; label: string; children: ReactNode }) {
    return (
        <div className="flex items-start gap-4">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style={{ background: 'var(--site-secondary)', color: 'var(--site-primary)' }}>
                {icon}
            </span>
            <div className="min-w-0">
                <p className="text-xs font-semibold tracking-wide uppercase opacity-55">{label}</p>
                <div className="mt-0.5 text-[16px] break-words whitespace-pre-line">{children}</div>
            </div>
        </div>
    );
}

export function ContactSection({ content, editing }: { content: ContactContent; editing?: boolean }) {
    const hasDetails = content.phone || content.email || content.address || content.hours || content.website;
    const tel = content.phone ? `tel:${content.phone.replace(/[^0-9+]/g, '')}` : '';
    const primaryHref = tel || (content.email ? `mailto:${content.email}` : '');

    return (
        <section id="contact" style={{ background: 'color-mix(in srgb, var(--site-secondary) 40%, var(--site-bg))' }}>
            <div className="mx-auto grid max-w-6xl gap-12 px-6 py-20 @3xl:grid-cols-5 @3xl:px-10 @3xl:py-24">
                <div className="@3xl:col-span-2">
                    <SectionHeading>{content.heading}</SectionHeading>
                    {content.intro && <p className="mt-4 text-[16px] leading-relaxed opacity-75">{content.intro}</p>}
                    {content.cta_text && primaryHref && (
                        <div className="mt-8">
                            <SiteButton href={primaryHref}>{content.cta_text}</SiteButton>
                        </div>
                    )}
                </div>
                <div className="space-y-6 @3xl:col-span-3">
                    {!hasDetails && editing && <Placeholder>Add your phone, email or address so customers can reach you.</Placeholder>}
                    {content.phone && (
                        <Row icon={<Phone className="h-4.5 w-4.5" />} label="Phone">
                            <a href={tel} className="hover:underline">
                                {content.phone}
                            </a>
                        </Row>
                    )}
                    {content.email && (
                        <Row icon={<Mail className="h-4.5 w-4.5" />} label="Email">
                            <a href={`mailto:${content.email}`} className="hover:underline">
                                {content.email}
                            </a>
                        </Row>
                    )}
                    {content.address && (
                        <Row icon={<MapPin className="h-4.5 w-4.5" />} label="Address">
                            {content.address}
                        </Row>
                    )}
                    {content.hours && (
                        <Row icon={<Clock className="h-4.5 w-4.5" />} label="Opening hours">
                            {content.hours}
                        </Row>
                    )}
                    {content.website && /^https?:\/\//i.test(content.website) && (
                        <Row icon={<Globe className="h-4.5 w-4.5" />} label="Website">
                            <a href={content.website} target="_blank" rel="noopener noreferrer" className="hover:underline">
                                {content.website.replace(/^https?:\/\/(www\.)?/i, '').replace(/\/$/, '')}
                            </a>
                        </Row>
                    )}
                </div>
            </div>
        </section>
    );
}
