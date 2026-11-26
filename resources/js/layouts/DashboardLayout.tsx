import { useEffect, useState, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Globe, LayoutDashboard, LogOut, Menu, Settings, User, X } from 'lucide-react';
import { useToast } from '@/components/ui/Toast';
import { Logo } from '@/components/Logo';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

const NAV = [
    { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, match: ['/dashboard'] },
    { href: '/websites', label: 'My Websites', icon: Globe, match: ['/websites', '/businesses'] },
    { href: '/settings', label: 'Settings', icon: Settings, match: ['/settings'] },
    { href: '/profile', label: 'Profile', icon: User, match: ['/profile'] },
];

export function useFlashToasts() {
    const { flash } = usePage<SharedProps>().props;
    const toast = useToast();
    useEffect(() => {
        if (flash?.success) toast(flash.success, 'success');
        if (flash?.error) toast(flash.error, 'error');
    }, [flash?.success, flash?.error, toast]);
}

export default function DashboardLayout({ title, children, wide = false }: { title: string; children: ReactNode; wide?: boolean }) {
    const { url, props } = usePage<SharedProps>();
    const user = props.auth.user;
    const [open, setOpen] = useState(false);
    useFlashToasts();

    const isActive = (match: string[]) => match.some((m) => url === m || url.startsWith(m + '/') || url.startsWith(m + '?'));

    const sidebar = (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center px-5">
                <Link href="/dashboard" className="flex items-center gap-2.5">
                    <Logo />
                </Link>
            </div>
            <nav className="flex-1 space-y-1 px-3 py-4" aria-label="Main">
                {NAV.map((item) => {
                    const active = isActive(item.match);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={() => setOpen(false)}
                            className={cn(
                                'flex items-center gap-3 rounded-[10px] px-3 py-2 text-sm font-medium transition-colors',
                                active ? 'bg-accent-soft text-accent' : 'text-muted hover:bg-gray-100 hover:text-ink',
                            )}
                            aria-current={active ? 'page' : undefined}
                        >
                            <item.icon className="h-4.5 w-4.5" />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>
            {user && (
                <div className="border-t border-line p-3">
                    <div className="flex items-center gap-3 rounded-[10px] px-2 py-2">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent text-[13px] font-semibold text-white">{user.name.charAt(0).toUpperCase()}</div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-ink">{user.name}</p>
                            <p className="truncate text-xs text-muted">{user.email}</p>
                        </div>
                        <button
                            onClick={() => router.post('/logout')}
                            className="rounded-lg p-1.5 text-muted hover:bg-gray-100 hover:text-ink"
                            title="Log out"
                            aria-label="Log out"
                        >
                            <LogOut className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );

    return (
        <div className="min-h-screen bg-canvas">
            <Head title={title} />
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 border-r border-line bg-white lg:block">{sidebar}</aside>

            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-gray-900/30" onClick={() => setOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-64 animate-slide-up bg-white shadow-pop">
                        <button onClick={() => setOpen(false)} className="absolute top-4 right-3 rounded-lg p-1.5 text-muted" aria-label="Close menu">
                            <X className="h-4 w-4" />
                        </button>
                        {sidebar}
                    </aside>
                </div>
            )}

            <div className="lg:pl-60">
                <div className="sticky top-0 z-20 flex h-14 items-center border-b border-line bg-white/90 px-4 backdrop-blur lg:hidden">
                    <button onClick={() => setOpen(true)} className="rounded-lg p-1.5 text-ink" aria-label="Open menu">
                        <Menu className="h-5 w-5" />
                    </button>
                    <div className="ml-3">
                        <Logo />
                    </div>
                </div>
                <main className={cn('mx-auto px-4 py-8 sm:px-6 lg:px-10 lg:py-10', wide ? 'max-w-7xl' : 'max-w-6xl')}>{children}</main>
            </div>
        </div>
    );
}
