import { useEffect, useRef, useState } from 'react';
import { Sparkles } from 'lucide-react';
import type { RendererData } from '@/types';
import { WebsiteRenderer } from '@/website-renderer/WebsiteRenderer';

const RENDER_WIDTH = 1280;

/** A live, scaled-down render of the website (not a static screenshot). */
export function WebsiteThumbnail({ data, generating }: { data: RendererData | null; generating?: boolean }) {
    const box = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0.25);

    useEffect(() => {
        if (!box.current) return;
        const obs = new ResizeObserver(([entry]) => setScale(entry.contentRect.width / RENDER_WIDTH));
        obs.observe(box.current);
        return () => obs.disconnect();
    }, []);

    return (
        <div ref={box} className="relative aspect-[16/10] w-full overflow-hidden bg-gray-50">
            {data ? (
                <div className="pointer-events-none absolute top-0 left-0 origin-top-left select-none" style={{ width: RENDER_WIDTH, transform: `scale(${scale})` }} aria-hidden inert>
                    <WebsiteRenderer data={data} />
                </div>
            ) : (
                <div className="flex h-full flex-col items-center justify-center gap-2 text-muted">
                    <Sparkles className="h-5 w-5 text-accent/70" />
                    <span className="text-xs">{generating ? 'Generating…' : 'Not generated yet'}</span>
                </div>
            )}
        </div>
    );
}
