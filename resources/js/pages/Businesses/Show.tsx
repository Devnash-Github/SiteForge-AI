import { useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Plus, Trash2 } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Button, LinkButton } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/Modal';
import { WebsiteCard } from '@/components/dashboard/WebsiteCard';
import { BusinessFields, toFormData } from '@/components/creation/BusinessFields';
import type { BusinessSummary, WebsiteCardData } from '@/types';

interface Props {
    business: BusinessSummary;
    websites: WebsiteCardData[];
    counts: { sources: number; facts: number; media: number };
}

export default function BusinessShow({ business, websites, counts }: Props) {
    const form = useForm(toFormData(business));
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/businesses/${business.id}`, { preserveScroll: true });
    };

    return (
        <DashboardLayout title={business.name}>
            <LinkButton href="/businesses" variant="ghost" size="sm" icon={<ArrowLeft className="h-4 w-4" />} className="-ml-3 mb-4">
                Businesses
            </LinkButton>
            <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">{business.name}</h1>
                    <p className="mt-1 text-sm text-muted">
                        {business.city} · {counts.facts} confirmed facts · {counts.sources} sources · {counts.media} photos
                    </p>
                </div>
                <div className="flex gap-2">
                    <LinkButton href={`/businesses/${business.id}/research`} variant="secondary" icon={<ClipboardCheck className="h-4 w-4" />}>
                        Review information
                    </LinkButton>
                    <Button variant="secondary" icon={<Plus className="h-4 w-4" />} onClick={() => router.post(`/businesses/${business.id}/websites`)}>
                        New website
                    </Button>
                </div>
            </div>

            <div className="grid gap-8 lg:grid-cols-3">
                <form onSubmit={submit} className="lg:col-span-2" noValidate>
                    <Card>
                        <CardHeader title="Business details" description="New links you add here are read automatically." />
                        <BusinessFields data={form.data} errors={form.errors as Record<string, string>} setData={(k, v) => form.setData(k, v)} />
                        <div className="mt-8 flex justify-end">
                            <Button type="submit" loading={form.processing} disabled={!form.isDirty}>
                                Save changes
                            </Button>
                        </div>
                    </Card>
                </form>

                <div className="space-y-6">
                    <div>
                        <h2 className="mb-3 text-[15px] font-semibold text-ink">Websites</h2>
                        <div className="space-y-4">
                            {websites.length === 0 && <p className="text-sm text-muted">No websites yet.</p>}
                            {websites.map((w) => (
                                <WebsiteCard key={w.id} website={w} />
                            ))}
                        </div>
                    </div>
                    <Card>
                        <CardHeader title="Delete business" description="Removes the business, its files, facts and websites." />
                        <Button variant="danger" icon={<Trash2 className="h-4 w-4" />} onClick={() => setConfirmDelete(true)}>
                            Delete business
                        </Button>
                    </Card>
                </div>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                danger
                title={`Delete ${business.name}?`}
                message="This permanently deletes the business, uploaded files, collected information and all of its websites, including published ones."
                confirmLabel="Delete business"
                loading={deleting}
                onCancel={() => setConfirmDelete(false)}
                onConfirm={() => {
                    setDeleting(true);
                    router.delete(`/businesses/${business.id}`, { onFinish: () => setDeleting(false) });
                }}
            />
        </DashboardLayout>
    );
}
