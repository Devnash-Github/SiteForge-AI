import type { MouseEvent, ReactNode } from 'react';
import type { Asset, ContactContent, RendererData, Section } from '@/types';
import { AboutSection } from './AboutSection';
import { ContactSection } from './ContactSection';
import { FooterSection } from './FooterSection';
import { GallerySection } from './GallerySection';
import { HeroSection } from './HeroSection';
import { ServicesSection } from './ServicesSection';
import type { AssetMap } from './shared';
import { themeVariables } from './theme';

const NAV_LABELS: Partial<Record<Section['type'], string>> = { about: 'About', services: 'Services', gallery: 'Gallery', contact: 'Contact' };

interface RendererProps {
    data: RendererData;
    /** Editor mode: shows placeholders for empty sections and enables selection. */
    editing?: boolean;
    selectedId?: number | null;
    onSelect?: (id: number) => void;
    /** Extra assets (e.g. every uploaded image in the studio) merged into the map. */
    extraAssets?: Asset[];
    showHeader?: boolean;
}

function toAssetMap(assets: RendererData['assets'], extra: Asset[] = []): AssetMap {
    const map: AssetMap = {};
    const list = Array.isArray(assets) ? assets : Object.values(assets ?? {});
    for (const a of [...list, ...extra]) map[a.id] = a;
    return map;
}

/**
 * Renders a website from validated structured JSON using predefined
 * components. It never renders HTML from content: all text goes through
 * React's escaping. The same renderer powers the studio, previews,
 * dashboard thumbnails and the public site. Layout uses container queries,
 * so the mobile preview is accurate inside a narrow frame.
 */
export function WebsiteRenderer({ data, editing = false, selectedId, onSelect, extraAssets, showHeader = true }: RendererProps) {
    const assets = toAssetMap(data.assets, extraAssets);
    const sections = editing ? data.sections : data.sections.filter((s) => s.visible);
    const visible = data.sections.filter((s) => s.visible);
    const nav = visible.filter((s) => NAV_LABELS[s.type]).map((s) => ({ id: s.type, label: NAV_LABELS[s.type] as string }));
    const contact = visible.find((s) => s.type === 'contact')?.content as ContactContent | undefined;

    const render = (section: Section): ReactNode => {
        switch (section.type) {
            case 'hero':
                return <HeroSection content={section.content} assets={assets} />;
            case 'about':
                return <AboutSection content={section.content} assets={assets} editing={editing} />;
            case 'services':
                return section.content.items.length || editing ? <ServicesSection content={section.content} editing={editing} /> : null;
            case 'gallery':
                return <GallerySection content={section.content} assets={assets} editing={editing} />;
            case 'contact':
                return <ContactSection content={section.content} editing={editing} />;
            case 'footer':
                return <FooterSection content={section.content} nav={nav} contact={contact} />;
            default:
                return null; // unknown section types are never rendered
        }
    };

    const onClickCapture = (e: MouseEvent) => {
        if (!editing) return;
        // Links inside the editor canvas must not navigate away.
        if ((e.target as HTMLElement).closest('a')) e.preventDefault();
    };

    return (
        <div
            className="@container min-h-full w-full"
            style={{ ...themeVariables(data.theme), background: 'var(--site-bg)', color: 'var(--site-text)', fontFamily: 'var(--site-font)' }}
            onClickCapture={onClickCapture}
        >
            {showHeader && (
                <header className="border-b" style={{ borderColor: 'color-mix(in srgb, var(--site-text) 10%, transparent)', background: 'var(--site-bg)' }}>
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4 @3xl:px-10">
                        <a href="#hero" className="truncate text-[17px] font-semibold" style={{ fontFamily: 'var(--site-heading-font)' }}>
                            {data.business.name}
                        </a>
                        {nav.length > 0 && (
                            <nav aria-label="Main" className="hidden items-center gap-7 text-sm font-medium opacity-80 @3xl:flex">
                                {nav.map((n) => (
                                    <a key={n.id} href={`#${n.id}`} className="hover:opacity-100">
                                        {n.label}
                                    </a>
                                ))}
                            </nav>
                        )}
                    </div>
                </header>
            )}

            {sections.length === 0 && (
                <div className="px-6 py-32 text-center text-sm opacity-60">This website has no visible sections yet.</div>
            )}

            {sections.map((section) => {
                const node = render(section);
                if (!editing) return <div key={section.id}>{node}</div>;
                const selected = selectedId === section.id;
                return (
                    <div
                        key={section.id}
                        onClick={() => onSelect?.(section.id)}
                        className={`group relative cursor-pointer transition-opacity ${section.visible ? '' : 'opacity-40'}`}
                        data-section-id={section.id}
                    >
                        <div className={`pointer-events-none absolute inset-0 z-10 transition-shadow ${selected ? 'shadow-[inset_0_0_0_2px_#635BFF]' : 'group-hover:shadow-[inset_0_0_0_2px_rgba(99,91,255,0.35)]'}`} />
                        {!section.visible && (
                            <span className="absolute top-3 right-3 z-10 rounded-md bg-gray-900/80 px-2 py-1 text-[11px] font-medium text-white">Hidden</span>
                        )}
                        {node ?? <div className="px-6 py-10 text-center text-sm opacity-50">Empty {section.type} section</div>}
                    </div>
                );
            })}
        </div>
    );
}
