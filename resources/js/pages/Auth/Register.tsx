import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Field';

export default function Register() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    const pw = form.data.password;
    const checks = [
        { ok: pw.length >= 8, label: '8+ characters' },
        { ok: /[a-zA-Z]/.test(pw), label: 'a letter' },
        { ok: /\d/.test(pw), label: 'a number' },
    ];

    return (
        <AuthLayout
            title="Create your account"
            subtitle="Build your first website in a few minutes."
            footer={
                <>
                    Already have an account?{' '}
                    <Link href="/login" className="font-medium text-accent hover:underline">
                        Log in
                    </Link>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Input label="Your name" autoComplete="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required autoFocus />
                <Input label="Email" type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} required />
                <div>
                    <Input label="Password" type="password" autoComplete="new-password" value={pw} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password} required />
                    {!form.errors.password && (
                        <p className="mt-1.5 text-xs text-muted">
                            Use{' '}
                            {checks.map((c, i) => (
                                <span key={c.label} className={c.ok ? 'text-emerald-600' : ''}>
                                    {c.label}
                                    {i < checks.length - 1 ? ', ' : '.'}
                                </span>
                            ))}
                        </p>
                    )}
                </div>
                <Input
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    error={form.errors.password_confirmation}
                    required
                />
                <Button type="submit" className="w-full" loading={form.processing}>
                    Create account
                </Button>
            </form>
        </AuthLayout>
    );
}
