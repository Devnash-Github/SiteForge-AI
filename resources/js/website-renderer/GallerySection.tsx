import type { GalleryContent } from '@/types';
import { assetFor, Placeholder, SectionHeading, type AssetMap } from './shared';

export function GallerySection({ content, assets, editing }: { content: GalleryContent; assets: AssetMap; editing?: boolean }) {
    const images = content.image_ids.map((id) => assetFor(assets, id)).filter((a): a is NonNullable<typeof a> => a !== null);

    if (images.length === 0 && !editing) return null;

    return (
        <section id="gallery" className="mx-auto max-w-6xl px-6 py-20 @3xl:px-10 @3xl:py-24">
            <SectionHeading>{content.title}</SectionHeading>
            {images.length === 0 ? (
                <div className="mt-10">
                    <Placeholder>Choose photos for the gallery in the editor.</Placeholder>
                </div>
            ) : (
                <div className="mt-10 grid grid-cols-2 gap-3 @3xl:grid-cols-3 @3xl:gap-4">
                    {images.map((img, i) => (
                        <img
                            key={img.id}
                            src={img.url}
                            alt={img.alt}
                            loading="lazy"
                            className={`h-full w-full rounded-xl object-cover ${i === 0 && images.length >= 5 ? 'col-span-2 row-span-2 aspect-square' : 'aspect-square'}`}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}
