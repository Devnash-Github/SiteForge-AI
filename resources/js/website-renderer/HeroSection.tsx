import type { HeroContent } from '@/types';
import { assetFor, SiteButton, type AssetMap } from './shared';

export function HeroSection({ content, assets }: { content: HeroContent; assets: AssetMap }) {
    const image = assetFor(assets, content.image_id);

    if (image) {
        return (
            <section id="hero" className="relative isolate overflow-hidden">
                <img src={image.url} alt={image.alt} className="absolute inset-0 -z-10 h-full w-full object-cover" />
                <div className="absolute inset-0 -z-10 bg-linear-to-r from-black/70 via-black/45 to-black/20" />
                <div className="mx-auto max-w-6xl px-6 py-24 @3xl:px-10 @3xl:py-36">
                    <div className="max-w-2xl text-white">
                        <h1 className="text-[36px] leading-[1.1] font-semibold tracking-tight @3xl:text-[56px]" style={{ fontFamily: 'var(--site-heading-font)' }}>
                            {content.headline}
                        </h1>
                        {content.subheadline && <p className="mt-5 text-[17px] leading-relaxed text-white/85 @3xl:text-[19px]">{content.subheadline}</p>}
                        {content.cta_text && (
                            <div className="mt-9">
                                <SiteButton href={content.cta_url}>{content.cta_text}</SiteButton>
                            </div>
                        )}
                    </div>
                </div>
            </section>
        );
    }

    return (
        <section id="hero" style={{ background: 'var(--site-secondary)' }}>
            <div className="mx-auto max-w-6xl px-6 py-20 @3xl:px-10 @3xl:py-32">
                <div className="max-w-3xl">
                    <h1 className="text-[36px] leading-[1.1] font-semibold tracking-tight @3xl:text-[56px]" style={{ fontFamily: 'var(--site-heading-font)' }}>
                        {content.headline}
                    </h1>
                    {content.subheadline && <p className="mt-5 text-[17px] leading-relaxed opacity-75 @3xl:text-[19px]">{content.subheadline}</p>}
                    {content.cta_text && (
                        <div className="mt-9">
                            <SiteButton href={content.cta_url}>{content.cta_text}</SiteButton>
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
