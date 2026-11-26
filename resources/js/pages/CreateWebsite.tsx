import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ArrowLeft, ArrowRight, ShieldCheck } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Button, LinkButton } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { FileUploader } from '@/components/ui/FileUploader';
import { BusinessFields, toFormData, type BusinessFormData } from '@/components/creation/BusinessFields';

interface Props {
    limits: { max_upload_mb: number; max_files: number };
}

type FormShape = BusinessFormData & { files: File[] };

export default function CreateWebsite({ limits }: Props) {
    const form = useForm<FormShape>({ ...toFormData(), files: [] });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/businesses', { forceFormData: true, preserveScroll: true });
    };

    const errors = form.errors as Record<string, string>;
    const fileErrors: Record<number, string> = {};
    Object.entries(errors).forEach(([k, v]) => {
        const m = k.match(/^files\.(\d+)$/);
        if (m) fileErrors[Number(m[1])] = v;
    });

    return (
        <DashboardLayout title="Create website">
            <div className="mx-auto max-w-3xl">
                <LinkButton href="/dashboard" variant="ghost" size="sm" icon={<ArrowLeft className="h-4 w-4" />} className="-ml-3 mb-4">
                    Back
                </LinkButton>
                <h1 className="text-2xl font-semibold tracking-tight text-ink">Create a website</h1>
                <p className="mt-1.5 mb-8 text-sm text-muted">Tell us what you already have. Only the name and city are required — everything else helps us write a better site.</p>

                <form onSubmit={submit} noValidate>
                    <Card className="space-y-8">
                        <BusinessFields data={form.data} errors={errors} setData={(k, v) => form.setData(k, v)} />

                        <section className="space-y-3">
                            <div>
                                <h3 className="text-sm font-semibold text-ink">Documents and photos</h3>
                                <p className="mt-0.5 text-xs text-muted">Menus, brochures, price lists (PDF) and photos of your business or products.</p>
                            </div>
                            <FileUploader
                                files={form.data.files}
                                onChange={(files) => form.setData('files', files)}
                                maxSizeMb={limits.max_upload_mb}
                                maxFiles={limits.max_files}
                                progress={form.progress?.percentage ?? null}
                                error={errors.files}
                                fileErrors={fileErrors}
                            />
                        </section>
                    </Card>

                    <div className="mt-6 flex flex-wrap items-center justify-between gap-4">
                        <p className="flex items-center gap-2 text-xs text-muted">
                            <ShieldCheck className="h-4 w-4 text-emerald-600" />
                            You review every fact before anything is published.
                        </p>
                        <Button type="submit" size="lg" loading={form.processing}>
                            Continue
                            <ArrowRight className="h-4 w-4" />
                        </Button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}
