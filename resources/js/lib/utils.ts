export function cn(...classes: (string | false | null | undefined)[]): string {
    return classes.filter(Boolean).join(' ');
}

export function formatRelative(iso: string | null | undefined): string {
    if (!iso) return '—';
    const date = new Date(iso);
    const diff = (Date.now() - date.getTime()) / 1000;
    if (diff < 45) return 'just now';
    if (diff < 3600) return `${Math.round(diff / 60)} min ago`;
    if (diff < 86400) return `${Math.round(diff / 3600)} h ago`;
    if (diff < 86400 * 7) return `${Math.round(diff / 86400)} d ago`;
    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function greeting(): string {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
}

export function debounce<A extends unknown[]>(fn: (...args: A) => void, ms: number) {
    let t: ReturnType<typeof setTimeout> | undefined;
    const wrapped = (...args: A) => {
        if (t) clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
    wrapped.cancel = () => t && clearTimeout(t);
    return wrapped;
}
