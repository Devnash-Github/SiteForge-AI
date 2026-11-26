import { Check } from 'lucide-react';
import type { AboutContent } from '@/types';
import { assetFor, Paragraphs, Placeholder, SectionHeading, type AssetMap } from './shared';

export function AboutSection({ content, assets, editing }: { content: AboutContent; assets: AssetMap; editing?: boolean }) {
    const image = assetFor(assets, content.image_id);

    return (
        <section id="about" className="mx-auto max-w-6xl px-6 py-20 @3xl:px-10 @3xl:py-24">
            <div className={`grid gap-12 ${image ? '@3xl:grid-cols-2 @3xl:items-center' : ''}`}>
                <div className={image ? '' : 'max-w-3xl'}>
                    <SectionHeading>{content.heading}</SectionHeading>
                    <div className="mt-6 space-y-4 text-[16px] leading-relaxed opacity-80 @3xl:text-[17px]">
                        {content.body ? <Paragraphs text={content.body} /> : editing ? <Placeholder>Add a short description of the business.</Placeholder> : null}
                    </div>
                    {content.highlights.length > 0 && (
                        <ul className="mt-8 grid gap-3 @xl:grid-cols-2">
                            {content.highlights.map((h) => (
                                <li key={h} className="flex items-start gap-2.5 text-[15px]">
                                    <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full" style={{ background: 'var(--site-secondary)', color: 'var(--site-primary)' }}>
                                        <Check className="h-3 w-3" strokeWidth={3} />
                                    </span>
                                    {h}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                {image && <img src={image.url} alt={image.alt} className="aspect-[4/3] w-full rounded-2xl object-cover" />}
            </div>
        </section>
    );
}
