import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Checkbox, Select, Textarea } from '@/components/ui/Field';
import { Modal } from '@/components/ui/Modal';
import { Notice } from '@/components/ui/States';

const TONE_LABELS: Record<string, string> = {
    professional: 'Professional',
    friendly: 'Friendly',
    premium: 'Premium',
    simple: 'Simple and direct',
};

interface Props {
    open: boolean;
    onClose: () => void;
    websiteId: number;
    aiAvailable: boolean;
    tones?: string[];
    replacing?: boolean;
    warnings?: string[];
    defaults?: { tone?: string; instructions?: string };
}

export function GenerateDialog({ open, onClose, websiteId, aiAvailable, tones = ['professional', 'friendly', 'premium', 'simple'], replacing, warnings = [], defaults }: Props) {
    const [tone, setTone] = useState(defaults?.tone ?? 'professional');
    const [instructions, setInstructions] = useState(defaults?.instructions ?? '');
    const [useAi, setUseAi] = useState(aiAvailable);
    const [busy, setBusy] = useState(false);

    const submit = () => {
        setBusy(true);
        router.post(`/websites/${websiteId}/generate`, { tone, instructions, use_ai: useAi }, { onFinish: () => setBusy(false) });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={replacing ? 'Regenerate website' : 'Generate website'}
            description="We write the copy and lay out your site from your confirmed facts."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={submit} loading={busy} icon={<Sparkles className="h-4 w-4" />}>
                        {replacing ? 'Regenerate' : 'Generate'}
                    </Button>
                </>
            }
        >
            <div className="space-y-5">
                {!aiAvailable && (
                    <Notice tone="warning" icon={<AlertTriangle className="h-4 w-4" />} title="AI writing is not configured">
                        The site will be built from your facts with simple, neutral wording. Add a <code className="rounded bg-amber-100 px-1">GEMINI_API_KEY</code> or <code className="rounded bg-amber-100 px-1">GROQ_API_KEY</code> on the server to have AI write the copy.
                    </Notice>
                )}
                {warnings.map((w) => (
                    <Notice key={w} tone="warning" icon={<AlertTriangle className="h-4 w-4" />}>
                        {w}
                    </Notice>
                ))}
                {replacing && <Notice tone="info">This replaces the current content of every section. Your theme and photos are kept.</Notice>}
                {aiAvailable && (
                    <>
                        <Select label="Tone of voice" value={tone} onChange={(e) => setTone(e.target.value)} options={tones.map((t) => ({ value: t, label: TONE_LABELS[t] ?? t }))} />
                        <Textarea
                            label="Anything else? (optional)"
                            value={instructions}
                            onChange={(e) => setInstructions(e.target.value)}
                            rows={3}
                            maxLength={500}
                            placeholder="e.g. Focus on families, keep it short, mention we are walk-in friendly"
                            hint="Style guidance only — the AI cannot add facts that you haven’t confirmed."
                        />
                        <Checkbox label="Use AI to write the copy" checked={useAi} onChange={setUseAi} description="Turn off for a plain version built directly from your facts." />
                    </>
                )}
            </div>
        </Modal>
    );
}
