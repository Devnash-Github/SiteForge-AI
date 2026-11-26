import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Checkbox, Input } from '@/components/ui/Field';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout
            title="Log in"
            subtitle="Welcome back. Log in to manage your websites."
            footer={
                <>
                    New to SiteForge AI?{' '}
                    <Link href="/register" className="font-medium text-accent hover:underline">
                        Create an account
                    </Link>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Input label="Email" type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} required autoFocus />
                <Input label="Password" type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password} required />
                <Checkbox label="Keep me logged in" checked={form.data.remember} onChange={(v) => form.setData('remember', v)} />
                <Button type="submit" className="w-full" loading={form.processing}>
                    Log in
                </Button>
            </form>
        </AuthLayout>
    );
}
