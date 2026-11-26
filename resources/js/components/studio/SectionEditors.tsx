import { useState, type ReactNode } from 'react';
import { ArrowDown, ArrowUp, ImagePlus, Plus, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Checkbox, Input, Textarea } from '@/components/ui/Field';
import type { AboutContent, ContactContent, FooterContent, GalleryContent, HeroContent, MediaItem, Section, ServicesContent } from '@/types';
import { ImagePicker } from './ImagePicker';

type Errors = Record<string, string>;

interface EditorProps<T> {
    content: T;
    onChange: (content: T) => void;
    errors: Errors;
    media: MediaItem[];
    openAssets: () => void;
}

function Group({ title, children }: { title?: string; children: ReactNode }) {
    return (
        <div className="space-y-4">
            {title && <p className="text-xs font-semibold tracking-wide text-muted uppercase">{title}</p>}
            {children}
        </div>
    );
}

function ImageField({ label, value, media, onChange, openAssets }: { label: string; value: number | null; media: MediaItem[]; onChange: (id: number | null) => void; openAssets: () => void }) {
    const [open, setOpen] = useState(false);
    const current = media.find((m) => m.id === value);
    return (
        <div className="space-y-1.5">
            <p className="text-[13px] font-medium text-ink">{label}</p>
            {current ? (
                <div className="group relative overflow-hidden rounded-[10px] border border-line">
                    <img src={current.url} alt={current.alt_text ?? ''} className="aspect-[16/9] w-full object-cover" />
                    <div className="absolute inset-x-0 bottom-0 flex gap-2 bg-linear-to-t from-black/60 to-transparent p-2">
                        <Button size="sm" variant="secondary" onClick={() => setOpen(true)}>
                            Replace
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => onChange(null)} icon={<X className="h-3.5 w-3.5" />}>
                            Remove
                        </Button>
                    </div>
                </div>
            ) : (
                <button type="button" onClick={() => setOpen(true)} className="flex w-full items-center justify-center gap-2 rounded-[10px] border border-dashed border-gray-300 py-5 text-sm text-muted hover:border-accent hover:text-accent">
                    <ImagePlus className="h-4 w-4" /> Choose image
                </button>
            )}
            <ImagePicker
                open={open}
                onClose={() => setOpen(false)}
                media={media}
                selected={value ? [value] : []}
                onSelect={(ids) => onChange(ids[0] ?? null)}
                onUploadClick={() => {
                    setOpen(false);
                    openAssets();
                }}
            />
        </div>
    );
}

function StringList({ label, items, onChange, placeholder, max }: { label: string; items: string[]; onChange: (v: string[]) => void; placeholder: string; max: number }) {
    return (
        <div className="space-y-2">
            <p className="text-[13px] font-medium text-ink">{label}</p>
            {items.map((item, i) => (
                <div key={i} className="flex gap-2">
                    <input
                        value={item}
                        onChange={(e) => onChange(items.map((x, j) => (j === i ? e.target.value : x)))}
                        maxLength={120}
                        className="h-9 flex-1 rounded-[10px] border border-line px-3 text-sm focus:border-accent focus:ring-2 focus:ring-accent/25 focus:outline-none"
                    />
                    <button onClick={() => onChange(items.filter((_, j) => j !== i))} className="rounded-lg px-2 text-muted hover:bg-gray-100 hover:text-red-600" aria-label="Remove">
                        <X className="h-4 w-4" />
                    </button>
                </div>
            ))}
            {items.length < max && (
                <Button size="sm" variant="ghost" icon={<Plus className="h-3.5 w-3.5" />} onClick={() => onChange([...items, ''])}>
                    {placeholder}
                </Button>
            )}
        </div>
    );
}

export function HeroEditor({ content, onChange, errors, media, openAssets }: EditorProps<HeroContent>) {
    const set = <K extends keyof HeroContent>(k: K, v: HeroContent[K]) => onChange({ ...content, [k]: v });
    return (
        <Group>
            <Textarea label="Headline" rows={2} value={content.headline} maxLength={120} onChange={(e) => set('headline', e.target.value)} error={errors.headline} />
            <Textarea label="Supporting text" rows={3} value={content.subheadline} maxLength={300} onChange={(e) => set('subheadline', e.target.value)} error={errors.subheadline} />
            <div className="grid grid-cols-2 gap-3">
                <Input label="Button text" value={content.cta_text} maxLength={40} onChange={(e) => set('cta_text', e.target.value)} error={errors.cta_text} />
                <Input label="Button link" value={content.cta_url} onChange={(e) => set('cta_url', e.target.value)} error={errors.cta_url} placeholder="#contact" />
            </div>
            <p className="-mt-2 text-xs text-muted">Use #contact, tel:+91…, mailto:… or a web address.</p>
            <ImageField label="Background image" value={content.image_id} media={media} onChange={(v) => set('image_id', v)} openAssets={openAssets} />
        </Group>
    );
}

export function AboutEditor({ content, onChange, errors, media, openAssets }: EditorProps<AboutContent>) {
    const set = <K extends keyof AboutContent>(k: K, v: AboutContent[K]) => onChange({ ...content, [k]: v });
    return (
        <Group>
            <Input label="Heading" value={content.heading} maxLength={100} onChange={(e) => set('heading', e.target.value)} error={errors.heading} />
            <Textarea label="Description" rows={7} value={content.body} maxLength={2000} onChange={(e) => set('body', e.target.value)} error={errors.body} hint="Leave a blank line between paragraphs." />
            <StringList label="Highlights" items={content.highlights} onChange={(v) => set('highlights', v)} placeholder="Add highlight" max={6} />
            <ImageField label="Image" value={content.image_id} media={media} onChange={(v) => set('image_id', v)} openAssets={openAssets} />
        </Group>
    );
}

export function ServicesEditor({ content, onChange, errors }: EditorProps<ServicesContent>) {
    const set = <K extends keyof ServicesContent>(k: K, v: ServicesContent[K]) => onChange({ ...content, [k]: v });
    const setItem = (i: number, patch: Partial<ServicesContent['items'][number]>) => set('items', content.items.map((it, j) => (j === i ? { ...it, ...patch } : it)));
    const moveItem = (i: number, d: number) => {
        const items = [...content.items];
        const j = i + d;
        if (j < 0 || j >= items.length) return;
        [items[i], items[j]] = [items[j], items[i]];
        set('items', items);
    };
    return (
        <Group>
            <Input label="Section title" value={content.title} maxLength={100} onChange={(e) => set('title', e.target.value)} error={errors.title} />
            <Textarea label="Introduction" rows={2} value={content.intro} maxLength={400} onChange={(e) => set('intro', e.target.value)} error={errors.intro} />
            <div className="space-y-3">
                <p className="text-[13px] font-medium text-ink">Services ({content.items.length})</p>
                {content.items.map((item, i) => (
                    <div key={i} className="space-y-2 rounded-[10px] border border-line bg-gray-50/50 p-3">
                        <div className="flex items-center gap-1">
                            <input
                                value={item.name}
                                onChange={(e) => setItem(i, { name: e.target.value })}
                                maxLength={120}
                                placeholder="Service name"
                                aria-label="Service name"
                                className="h-9 flex-1 rounded-[8px] border border-line bg-white px-3 text-sm font-medium focus:border-accent focus:ring-2 focus:ring-accent/25 focus:outline-none"
                            />
                            <button onClick={() => moveItem(i, -1)} disabled={i === 0} className="rounded-md p-1.5 text-muted hover:bg-white disabled:opacity-30" aria-label="Move up">
                                <ArrowUp className="h-3.5 w-3.5" />
                            </button>
                            <button onClick={() => moveItem(i, 1)} disabled={i === content.items.length - 1} className="rounded-md p-1.5 text-muted hover:bg-white disabled:opacity-30" aria-label="Move down">
                                <ArrowDown className="h-3.5 w-3.5" />
                            </button>
                            <button onClick={() => set('items', content.items.filter((_, j) => j !== i))} className="rounded-md p-1.5 text-muted hover:bg-white hover:text-red-600" aria-label="Remove service">
                                <Trash2 className="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <textarea
                            value={item.description}
                            onChange={(e) => setItem(i, { description: e.target.value })}
                            maxLength={400}
                            rows={2}
                            placeholder="Short description (optional)"
                            aria-label="Service description"
                            className="block w-full resize-y rounded-[8px] border border-line bg-white px-3 py-2 text-sm focus:border-accent focus:ring-2 focus:ring-accent/25 focus:outline-none"
                        />
                    </div>
                ))}
                {errors.items && <p className="text-xs text-red-600">{errors.items}</p>}
                <Button size="sm" variant="secondary" icon={<Plus className="h-3.5 w-3.5" />} onClick={() => set('items', [...content.items, { name: '', description: '' }])} disabled={content.items.length >= 24}>
                    Add service
                </Button>
                <p className="text-xs text-muted">Empty service names are not saved.</p>
            </div>
        </Group>
    );
}

export function GalleryEditor({ content, onChange, errors, media, openAssets }: EditorProps<GalleryContent>) {
    const [open, setOpen] = useState(false);
    const images = content.image_ids.map((id) => media.find((m) => m.id === id)).filter(Boolean) as MediaItem[];
    return (
        <Group>
            <Input label="Section title" value={content.title} maxLength={100} onChange={(e) => onChange({ ...content, title: e.target.value })} error={errors.title} />
            <div className="space-y-2">
                <p className="text-[13px] font-medium text-ink">Photos ({images.length})</p>
                {images.length > 0 && (
                    <div className="grid grid-cols-3 gap-2">
                        {images.map((m) => (
                            <div key={m.id} className="group relative aspect-square overflow-hidden rounded-[8px]">
                                <img src={m.url} alt={m.alt_text ?? ''} className="h-full w-full object-cover" />
                                <button
                                    onClick={() => onChange({ ...content, image_ids: content.image_ids.filter((x) => x !== m.id) })}
                                    className="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white opacity-0 group-hover:opacity-100"
                                    aria-label="Remove from gallery"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
                <Button size="sm" variant="secondary" icon={<ImagePlus className="h-3.5 w-3.5" />} onClick={() => setOpen(true)}>
                    Choose photos
                </Button>
                {errors.image_ids && <p className="text-xs text-red-600">{errors.image_ids}</p>}
            </div>
            <ImagePicker
                open={open}
                onClose={() => setOpen(false)}
                media={media}
                selected={content.image_ids}
                multiple
                onSelect={(ids) => onChange({ ...content, image_ids: ids })}
                onUploadClick={() => {
                    setOpen(false);
                    openAssets();
                }}
            />
        </Group>
    );
}

export function ContactEditor({ content, onChange, errors }: EditorProps<ContactContent>) {
    const set = <K extends keyof ContactContent>(k: K, v: ContactContent[K]) => onChange({ ...content, [k]: v });
    return (
        <Group>
            <Input label="Heading" value={content.heading} maxLength={100} onChange={(e) => set('heading', e.target.value)} error={errors.heading} />
            <Textarea label="Introduction" rows={2} value={content.intro} maxLength={300} onChange={(e) => set('intro', e.target.value)} error={errors.intro} />
            <Input label="Phone" value={content.phone} onChange={(e) => set('phone', e.target.value)} error={errors.phone} inputMode="tel" />
            <Input label="Email" value={content.email} onChange={(e) => set('email', e.target.value)} error={errors.email} inputMode="email" />
            <Textarea label="Address" rows={2} value={content.address} maxLength={300} onChange={(e) => set('address', e.target.value)} error={errors.address} />
            <Textarea label="Opening hours" rows={2} value={content.hours} maxLength={400} onChange={(e) => set('hours', e.target.value)} error={errors.hours} />
            <Input label="Website" value={content.website} onChange={(e) => set('website', e.target.value)} error={errors.website} inputMode="url" />
            <Input label="Button text" value={content.cta_text} maxLength={40} onChange={(e) => set('cta_text', e.target.value)} error={errors.cta_text} hint="Calls the phone number, or emails if there is no phone." />
        </Group>
    );
}

export function FooterEditor({ content, onChange, errors }: EditorProps<FooterContent>) {
    return (
        <Group>
            <Input label="Business name" value={content.business_name} maxLength={160} onChange={(e) => onChange({ ...content, business_name: e.target.value })} error={errors.business_name} />
            <Input label="Tagline" value={content.tagline} maxLength={200} onChange={(e) => onChange({ ...content, tagline: e.target.value })} error={errors.tagline} />
            <Checkbox label="Show contact details in the footer" checked={content.show_contact} onChange={(v) => onChange({ ...content, show_contact: v })} />
        </Group>
    );
}

export function SectionEditor({ section, onChange, errors, media, openAssets }: { section: Section; onChange: (c: Section['content']) => void; errors: Errors; media: MediaItem[]; openAssets: () => void }) {
    const common = { errors, media, openAssets };
    switch (section.type) {
        case 'hero':
            return <HeroEditor content={section.content} onChange={onChange} {...common} />;
        case 'about':
            return <AboutEditor content={section.content} onChange={onChange} {...common} />;
        case 'services':
            return <ServicesEditor content={section.content} onChange={onChange} {...common} />;
        case 'gallery':
            return <GalleryEditor content={section.content} onChange={onChange} {...common} />;
        case 'contact':
            return <ContactEditor content={section.content} onChange={onChange} {...common} />;
        case 'footer':
            return <FooterEditor content={section.content} onChange={onChange} {...common} />;
    }
}
