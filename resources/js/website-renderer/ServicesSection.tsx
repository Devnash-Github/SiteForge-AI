import type { ServicesContent } from '@/types';
import { Placeholder, SectionHeading } from './shared';

export function ServicesSection({ content, editing }: { content: ServicesContent; editing?: boolean }) {
    return (
        <section id="services" style={{ background: 'color-mix(in srgb, var(--site-secondary) 55%, var(--site-bg))' }}>
            <div className="mx-auto max-w-6xl px-6 py-20 @3xl:px-10 @3xl:py-24">
                <div className="max-w-2xl">
                    <SectionHeading>{content.title}</SectionHeading>
                    {content.intro && <p className="mt-4 text-[16px] leading-relaxed opacity-75">{content.intro}</p>}
                </div>
                {content.items.length === 0 ? (
                    editing ? (
                        <div className="mt-10">
                            <Placeholder>Add the services or products you offer.</Placeholder>
                        </div>
                    ) : null
                ) : (
                    <div className="mt-12 grid gap-5 @xl:grid-cols-2 @4xl:grid-cols-3">
                        {content.items.map((item, i) => (
                            <div key={`${item.name}-${i}`} className="rounded-2xl p-6" style={{ background: 'var(--site-bg)', boxShadow: '0 1px 3px rgb(0 0 0 / 0.06)' }}>
                                <div className="mb-4 h-1 w-10 rounded-full" style={{ background: 'var(--site-primary)' }} />
                                <h3 className="text-[18px] font-semibold" style={{ fontFamily: 'var(--site-heading-font)' }}>
                                    {item.name}
                                </h3>
                                {item.description && <p className="mt-2 text-[15px] leading-relaxed opacity-75">{item.description}</p>}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
