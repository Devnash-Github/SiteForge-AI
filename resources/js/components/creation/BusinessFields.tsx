import { useState } from 'react';
import { Briefcase, Camera, ChevronDown, Globe, Link2, MapPin, ThumbsUp } from 'lucide-react';
import { Input, Textarea } from '@/components/ui/Field';

export interface BusinessFormData {
    name: string;
    city: string;
    category: string;
    description: string;
    additional_info: string;
    website_url: string;
    instagram_url: string;
    facebook_url: string;
    linkedin_url: string;
    google_business_url: string;
    other_urls: string;
}

interface Props {
    data: BusinessFormData;
    errors: Partial<Record<string, string>>;
    setData: (key: keyof BusinessFormData, value: string) => void;
}

/** Shared by "Create website" and "Edit business". Kept short: only name and city are required. */
export function BusinessFields({ data, errors, setData }: Props) {
    const [moreOpen, setMoreOpen] = useState(Boolean(data.additional_info || data.other_urls || data.linkedin_url || data.google_business_url));
    const otherError = Object.entries(errors).find(([k]) => k.startsWith('other_urls'))?.[1];

    return (
        <div className="space-y-8">
            <section className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Input label="Business name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} placeholder="e.g. Glow Salon" required maxLength={160} />
                    <Input label="City or location" value={data.city} onChange={(e) => setData('city', e.target.value)} error={errors.city} placeholder="e.g. Bandra, Mumbai" required maxLength={120} icon={<MapPin className="h-4 w-4" />} />
                </div>
                <Input
                    label="Type of business"
                    value={data.category}
                    onChange={(e) => setData('category', e.target.value)}
                    error={errors.category}
                    placeholder="e.g. Hair salon, Bakery, Dental clinic"
                    hint="Optional — helps us find the right business online."
                    maxLength={120}
                />
                <Textarea
                    label="Describe the business"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    error={errors.description}
                    rows={4}
                    placeholder="What do you do, who do you serve, what makes you different? Include contact details, services or hours if you like."
                    hint="Optional. Anything you write here is treated as confirmed information."
                    maxLength={5000}
                />
            </section>

            <section className="space-y-4">
                <div>
                    <h3 className="text-sm font-semibold text-ink">Existing online presence</h3>
                    <p className="mt-0.5 text-xs text-muted">Optional. We read publicly available pages only.</p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Input label="Website" value={data.website_url} onChange={(e) => setData('website_url', e.target.value)} error={errors.website_url} placeholder="glowsalon.in" icon={<Globe className="h-4 w-4" />} inputMode="url" />
                    <Input label="Instagram" value={data.instagram_url} onChange={(e) => setData('instagram_url', e.target.value)} error={errors.instagram_url} placeholder="instagram.com/glowsalon" icon={<Camera className="h-4 w-4" />} inputMode="url" />
                    <Input label="Facebook" value={data.facebook_url} onChange={(e) => setData('facebook_url', e.target.value)} error={errors.facebook_url} placeholder="facebook.com/glowsalon" icon={<ThumbsUp className="h-4 w-4" />} inputMode="url" />
                    <Input
                        label="Google Business Profile"
                        value={data.google_business_url}
                        onChange={(e) => setData('google_business_url', e.target.value)}
                        error={errors.google_business_url}
                        placeholder="maps.app.goo.gl/…"
                        icon={<MapPin className="h-4 w-4" />}
                        inputMode="url"
                    />
                </div>

                <button type="button" onClick={() => setMoreOpen(!moreOpen)} className="flex items-center gap-1.5 text-[13px] font-medium text-accent">
                    <ChevronDown className={`h-4 w-4 transition-transform ${moreOpen ? 'rotate-180' : ''}`} />
                    {moreOpen ? 'Fewer options' : 'More links and details'}
                </button>

                {moreOpen && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input label="LinkedIn" value={data.linkedin_url} onChange={(e) => setData('linkedin_url', e.target.value)} error={errors.linkedin_url} placeholder="linkedin.com/company/…" icon={<Briefcase className="h-4 w-4" />} inputMode="url" />
                            <Textarea
                                label="Other links"
                                value={data.other_urls}
                                onChange={(e) => setData('other_urls', e.target.value)}
                                error={otherError}
                                rows={2}
                                placeholder="One per line — directory listings, menus, booking pages…"
                            />
                        </div>
                        <Textarea
                            label="Additional information"
                            value={data.additional_info}
                            onChange={(e) => setData('additional_info', e.target.value)}
                            error={errors.additional_info}
                            rows={3}
                            placeholder="Opening hours, prices, team, anything else customers should know."
                            maxLength={5000}
                        />
                    </div>
                )}
                <p className="flex items-center gap-1.5 text-xs text-muted">
                    <Link2 className="h-3.5 w-3.5" /> Links without https:// are fine — we add it for you.
                </p>
            </section>
        </div>
    );
}

export function toFormData(b?: Partial<Record<keyof BusinessFormData, unknown>> & { other_urls?: string[] | string }): BusinessFormData {
    const s = (v: unknown) => (typeof v === 'string' ? v : '');
    return {
        name: s(b?.name),
        city: s(b?.city),
        category: s(b?.category),
        description: s(b?.description),
        additional_info: s(b?.additional_info),
        website_url: s(b?.website_url),
        instagram_url: s(b?.instagram_url),
        facebook_url: s(b?.facebook_url),
        linkedin_url: s(b?.linkedin_url),
        google_business_url: s(b?.google_business_url),
        other_urls: Array.isArray(b?.other_urls) ? b.other_urls.join('\n') : s(b?.other_urls),
    };
}
