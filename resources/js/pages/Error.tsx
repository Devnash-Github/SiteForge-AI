import { Head, Link } from '@inertiajs/react';
import { Logo } from '@/components/Logo';

const MESSAGES: Record<number, { title: string; text: string }> = {
    403: { title: 'Access denied', text: 'You don’t have permission to view this page.' },
    404: { title: 'Page not found', text: 'This page doesn’t exist, or the website is not published.' },
    500: { title: 'Something went wrong', text: 'An unexpected error occurred. Please try again in a moment.' },
    503: { title: 'Temporarily unavailable', text: 'We’re doing some maintenance. Please check back soon.' },
};

export default function ErrorPage({ status }: { status: number }) {
    const m = MESSAGES[status] ?? MESSAGES[500];
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-canvas px-4 text-center">
            <Head title={m.title} />
            <Logo />
            <p className="mt-10 text-sm font-semibold text-accent">{status}</p>
            <h1 className="mt-2 text-2xl font-semibold tracking-tight text-ink">{m.title}</h1>
            <p className="mt-2 max-w-sm text-sm text-muted">{m.text}</p>
            <Link href="/" className="mt-8 text-sm font-medium text-accent hover:underline">
                Go to SiteForge AI
            </Link>
        </div>
    );
}
