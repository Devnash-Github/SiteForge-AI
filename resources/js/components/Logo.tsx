export function Logo({ size = 'md' }: { size?: 'md' | 'lg' }) {
    const box = size === 'lg' ? 'h-9 w-9 text-base' : 'h-7 w-7 text-[13px]';
    return (
        <span className="flex items-center gap-2.5">
            <span className={`flex items-center justify-center rounded-[8px] bg-accent font-bold text-white ${box}`} aria-hidden>
                S
            </span>
            <span className={`font-semibold tracking-tight text-ink ${size === 'lg' ? 'text-lg' : 'text-[15px]'}`}>
                SiteForge <span className="text-accent">AI</span>
            </span>
        </span>
    );
}
