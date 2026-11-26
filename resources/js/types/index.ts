export type SectionType = 'hero' | 'about' | 'services' | 'gallery' | 'contact' | 'footer';

export interface Theme {
    preset: string;
    primary: string;
    secondary: string;
    background: string;
    text: string;
    font: 'inter' | 'dm-sans' | 'playfair';
    button_style: 'rounded' | 'pill' | 'square';
}

export interface HeroContent {
    headline: string;
    subheadline: string;
    cta_text: string;
    cta_url: string;
    image_id: number | null;
}
export interface AboutContent {
    heading: string;
    body: string;
    highlights: string[];
    image_id: number | null;
}
export interface ServiceItem {
    name: string;
    description: string;
}
export interface ServicesContent {
    title: string;
    intro: string;
    items: ServiceItem[];
}
export interface GalleryContent {
    title: string;
    image_ids: number[];
}
export interface ContactContent {
    heading: string;
    intro: string;
    phone: string;
    email: string;
    address: string;
    hours: string;
    website: string;
    cta_text: string;
}
export interface FooterContent {
    business_name: string;
    tagline: string;
    show_contact: boolean;
}

export interface SectionContentMap {
    hero: HeroContent;
    about: AboutContent;
    services: ServicesContent;
    gallery: GalleryContent;
    contact: ContactContent;
    footer: FooterContent;
}

export type Section = {
    [K in SectionType]: { id: number; type: K; visible: boolean; content: SectionContentMap[K] };
}[SectionType];

export interface Asset {
    id: number;
    url: string;
    alt: string;
    width: number | null;
    height: number | null;
}

export interface RendererData {
    theme: Theme;
    sections: Section[];
    assets: Record<number, Asset> | Asset[];
    business: { name: string; city: string };
}

export interface WebsiteCardData {
    id: number;
    name: string;
    slug: string;
    status: 'draft' | 'published' | 'unpublished';
    generation_status: GenerationStatus;
    business: { id: number; name: string; city: string };
    updated_at: string | null;
    published_at: string | null;
    public_url: string | null;
    has_unpublished_changes: boolean;
    section_count: number;
    thumbnail: RendererData | null;
}

export type GenerationStatus = 'idle' | 'pending' | 'processing' | 'completed' | 'failed';

export interface StudioWebsite {
    id: number;
    name: string;
    slug: string;
    status: WebsiteCardData['status'];
    generation_status: GenerationStatus;
    generation_error: string | null;
    generation_mode: 'ai' | 'basic' | null;
    generation_preferences: { tone?: string; instructions?: string } | null;
    published_at: string | null;
    public_url: string;
    is_published: boolean;
    has_unpublished_changes: boolean;
    business: { id: number; name: string; city: string };
}

export interface MediaItem {
    id: number;
    url: string;
    file_name: string;
    alt_text: string | null;
    size: number;
    width: number | null;
    height: number | null;
    mime_type: string;
}

export interface BusinessSummary {
    id: number;
    name: string;
    city: string;
    category: string | null;
    description: string | null;
    additional_info: string | null;
    website_url: string | null;
    instagram_url: string | null;
    facebook_url: string | null;
    linkedin_url: string | null;
    google_business_url: string | null;
    other_urls: string[];
    status: 'draft' | 'researching' | 'ready';
    research_status: string;
    research_error: string | null;
    updated_at: string | null;
}

export type SourceStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'blocked';

export interface SourceItem {
    id: number;
    type: string;
    label: string;
    url: string | null;
    file_name: string | null;
    status: SourceStatus;
    error_message: string | null;
    identity_status: 'matched' | 'uncertain' | 'confirmed' | 'rejected' | null;
    match_reasons: string[];
    snippet: string | null;
    facts_found: number | null;
    ai_error: string | null;
    page_error: string | null;
    pages: number | null;
    content_length: number;
    created_at: string | null;
    processed_at: string | null;
}

export type FactStatus = 'extracted' | 'needs_review' | 'confirmed' | 'rejected';

export interface FactItem {
    id: number;
    key: string;
    label: string;
    value: string;
    normalized_value: string;
    status: FactStatus;
    source_id: number | null;
    source_type: string;
    source_label: string;
    extracted_at: string | null;
}

export interface ConflictOption {
    value: string;
    normalized: string;
    fact_ids: number[];
    sources: { id: number | null; type: string; label: string }[];
    confirmed: boolean;
}

export interface Conflict {
    key: string;
    label: string;
    options: ConflictOption[];
    may_keep_several: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface SharedProps {
    appName: string;
    auth: { user: { id: number; name: string; email: string } | null };
    flash: { success: string | null; error: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}
