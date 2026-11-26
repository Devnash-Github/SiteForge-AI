import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, Check } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Field';
import type { Conflict } from '@/types';

function ConflictCard({ conflict, businessId }: { conflict: Conflict; businessId: number }) {
    const [choice, setChoice] = useState<string | null>(null); // normalized value or 'manual'
    const [manual, setManual] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const submit = (payload: Record<string, unknown>) => {
        setBusy(true);
        setError(undefined);
        router.post(`/businesses/${businessId}/conflicts/resolve`, { key: conflict.key, ...payload }, {
            preserveScroll: true,
            onError: (errs) => setError(Object.values(errs)[0]),
            onFinish: () => setBusy(false),
        });
    };

    const resolve = () => {
        if (choice === 'manual') return submit({ value: manual });
        const option = conflict.options.find((o) => o.normalized === choice);
        if (option) submit({ fact_id: option.fact_ids[0] });
    };

    return (
        <div className="rounded-[12px] border border-amber-200 bg-white">
            <div className="flex items-center gap-2 border-b border-amber-100 bg-amber-50/70 px-4 py-2.5 rounded-t-[12px]">
                <AlertTriangle className="h-4 w-4 text-amber-600" />
                <p className="text-sm font-medium text-amber-900">Different {conflict.label.toLowerCase()} values found</p>
            </div>
            <fieldset className="space-y-2 p-4">
                <legend className="sr-only">Choose the correct {conflict.label}</legend>
                {conflict.options.map((o) => (
                    <label key={o.normalized} className={`flex cursor-pointer items-start gap-3 rounded-[10px] border px-3 py-2.5 transition-colors ${choice === o.normalized ? 'border-accent bg-accent-soft/40' : 'border-line hover:bg-gray-50'}`}>
                        <input type="radio" name={`conflict-${conflict.key}`} className="mt-1 accent-[#635BFF]" checked={choice === o.normalized} onChange={() => setChoice(o.normalized)} />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm break-words whitespace-pre-line text-ink">{o.value}</p>
                            <p className="mt-0.5 text-xs text-muted">
                                Found in: {o.sources.map((s) => s.label).join(', ')}
                                {o.confirmed && <span className="ml-1.5 text-emerald-700">· confirmed earlier</span>}
                            </p>
                        </div>
                    </label>
                ))}
                <label className={`flex cursor-pointer items-start gap-3 rounded-[10px] border px-3 py-2.5 ${choice === 'manual' ? 'border-accent bg-accent-soft/40' : 'border-line hover:bg-gray-50'}`}>
                    <input type="radio" name={`conflict-${conflict.key}`} className="mt-3 accent-[#635BFF]" checked={choice === 'manual'} onChange={() => setChoice('manual')} />
                    <div className="flex-1">
                        <p className="mb-1.5 text-sm text-ink">None of these — enter the correct value</p>
                        {choice === 'manual' && <Input value={manual} onChange={(e) => setManual(e.target.value)} placeholder={`Correct ${conflict.label.toLowerCase()}`} autoFocus />}
                    </div>
                </label>
                {error && <p className="text-xs font-medium text-red-600">{error}</p>}
                <div className="flex flex-wrap gap-2 pt-1">
                    <Button size="sm" icon={<Check className="h-3.5 w-3.5" />} onClick={resolve} loading={busy} disabled={!choice || (choice === 'manual' && !manual.trim())}>
                        Use this value
                    </Button>
                    {conflict.may_keep_several && (
                        <Button size="sm" variant="ghost" onClick={() => submit({ keep_all: true })} disabled={busy}>
                            Keep all — they’re all correct
                        </Button>
                    )}
                </div>
            </fieldset>
        </div>
    );
}

export function ConflictsPanel({ conflicts, businessId }: { conflicts: Conflict[]; businessId: number }) {
    if (conflicts.length === 0) return null;
    return (
        <section>
            <h2 className="mb-1 text-[15px] font-semibold text-ink">Needs your decision ({conflicts.length})</h2>
            <p className="mb-4 text-sm text-muted">Your sources disagree. Choose the correct value — we never pick one for you.</p>
            <div className="grid gap-4 xl:grid-cols-2">
                {conflicts.map((c) => (
                    <ConflictCard key={c.key} conflict={c} businessId={businessId} />
                ))}
            </div>
        </section>
    );
}
