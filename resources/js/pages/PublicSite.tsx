import { WebsiteRenderer } from '@/website-renderer/WebsiteRenderer';
import type { RendererData } from '@/types';

/** The public website. Title and description are rendered server-side in the HTML head. */
export default function PublicSite({ site }: { site: RendererData }) {
    return (
        <div className="min-h-screen">
            <WebsiteRenderer data={site} />
        </div>
    );
}
