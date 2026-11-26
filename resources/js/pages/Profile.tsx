import type { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, PageHeader } from '@/components/ui/Card';
import { Input } from '@/components/ui/Field';

export default function Profile({ user }: { user: { id: number; name: string; email: string } }) {
    const profile = useForm({ name: user.name, email: user.email });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <DashboardLayout title="Profile">
            <PageHeader title="Profile" description="Your account details." />
            <div className="grid max-w-3xl gap-6">
                <Card>
                    <CardHeader title="Personal information" />
                    <form
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            profile.put('/profile', { preserveScroll: true });
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input label="Name" value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} error={profile.errors.name} required />
                            <Input label="Email" type="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} error={profile.errors.email} required />
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" loading={profile.processing} disabled={!profile.isDirty}>
                                Save
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card>
                    <CardHeader title="Change password" description="Use at least 8 characters with a letter and a number." />
                    <form
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            password.put('/profile/password', { preserveScroll: true, onSuccess: () => password.reset() });
                        }}
                        className="space-y-4"
                    >
                        <Input label="Current password" type="password" autoComplete="current-password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} error={password.errors.current_password} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input label="New password" type="password" autoComplete="new-password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} error={password.errors.password} />
                            <Input label="Confirm new password" type="password" autoComplete="new-password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} error={password.errors.password_confirmation} />
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" loading={password.processing}>
                                Update password
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </DashboardLayout>
    );
}
