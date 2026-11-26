import { useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { CheckCircle2, CircleAlert } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, PageHeader } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Field';
import { Modal } from '@/components/ui/Modal';

interface Props {
    preferences: { default_tone: string };
    tones: string[];
    integrations: { ai: { configured: boolean; provider: string; model: string }; search: { configured: boolean; provider: string }; queue: string };
}

const TONE_LABELS: Record<string, string> = { professional: 'Professional', friendly: 'Friendly', premium: 'Premium', simple: 'Simple and direct' };

function Status({ ok, label, detail }: { ok: boolean; label: string; detail: string }) {
    return (
        <div className="flex items-start gap-3 py-3">
            {ok ? <CheckCircle2 className="mt-0.5 h-4.5 w-4.5 text-emerald-600" /> : <CircleAlert className="mt-0.5 h-4.5 w-4.5 text-amber-600" />}
            <div>
                <p className="text-sm font-medium text-ink">{label}</p>
                <p className="text-xs text-muted">{detail}</p>
            </div>
        </div>
    );
}

export default function Settings({ preferences, tones, integrations }: Props) {
    const form = useForm({ default_tone: preferences.default_tone });
    const del = useForm({ password: '' });
    const [deleting, setDeleting] = useState(false);

    return (
        <DashboardLayout title="Settings">
            <PageHeader title="Settings" description="Defaults for new websites and the status of this installation." />
            <div className="grid max-w-3xl gap-6">
                <Card>
                    <CardHeader title="Writing defaults" description="Used when you generate a website without choosing a tone." />
                    <form
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            form.put('/settings', { preserveScroll: true });
                        }}
                        className="flex items-end gap-3"
                    >
                        <Select wrapperClassName="flex-1" label="Default tone of voice" value={form.data.default_tone} onChange={(e) => form.setData('default_tone', e.target.value)} options={tones.map((t) => ({ value: t, label: TONE_LABELS[t] ?? t }))} error={form.errors.default_tone} />
                        <Button type="submit" loading={form.processing} disabled={!form.isDirty}>
                            Save
                        </Button>
                    </form>
                </Card>

                <Card>
                    <CardHeader title="Integrations" description="Configured by the server administrator through environment variables. Keys are never shown here." />
                    <div className="divide-y divide-line">
                        <Status ok={integrations.ai.configured} label={`AI writing (${integrations.ai.provider === 'groq' ? 'Groq' : 'Google Gemini'})`} detail={integrations.ai.configured ? `Configured · model ${integrations.ai.model}` : 'Not configured — websites are built with neutral wording. Set GEMINI_API_KEY or GROQ_API_KEY.'} />
                        <Status ok={integrations.search.configured} label="Web research" detail={integrations.search.configured ? `Configured · provider ${integrations.search.provider}` : 'Not configured — only information you provide is used. Set SEARCH_PROVIDER and SEARCH_API_KEY.'} />
                        <Status ok={integrations.queue !== 'sync'} label="Background processing" detail={`Queue driver: ${integrations.queue}${integrations.queue === 'database' ? ' · requires a running queue worker' : ''}`} />
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Delete account" description="Permanently deletes your account, businesses, files and websites." />
                    <Button variant="danger" onClick={() => setDeleting(true)}>
                        Delete account
                    </Button>
                </Card>
            </div>

            <Modal
                open={deleting}
                onClose={() => setDeleting(false)}
                title="Delete your account?"
                description="This cannot be undone. Published websites will stop working immediately."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setDeleting(false)}>
                            Cancel
                        </Button>
                        <Button variant="danger" loading={del.processing} onClick={() => del.delete('/settings/account')}>
                            Delete account
                        </Button>
                    </>
                }
            >
                <Input label="Confirm with your password" type="password" value={del.data.password} onChange={(e) => del.setData('password', e.target.value)} error={del.errors.password} autoComplete="current-password" />
            </Modal>
        </DashboardLayout>
    );
}
