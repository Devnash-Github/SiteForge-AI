import { useMemo, useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Check, CheckCheck, Pencil, Plus, Trash2, Undo2, X } from 'lucide-react';
import { FactStatusBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Select, Textarea } from '@/components/ui/Field';
import { Modal } from '@/components/ui/Modal';
import { Tabs } from '@/components/ui/Tabs';
import { Tooltip } from '@/components/ui/Tooltip';
import type { FactItem } from '@/types';

const GROUPS: { title: string; keys: string[] }[] = [
    { title: 'Contact details', keys: ['phone', 'email', 'address', 'opening_hours'] },
    { title: 'Services & products', keys: ['service', 'product'] },
    { title: 'About the business', keys: ['business_name', 'category', 'description', 'highlight'] },
];

type Filter = 'all' | 'review' | 'confirmed' | 'rejected';

function FactRow({ fact, businessId, inConflict }: { fact: FactItem; businessId: number; inConflict: boolean }) {
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState(fact.value);
    const [error, setError] = useState<string | undefined>();
    const url = `/businesses/${businessId}/facts/${fact.id}`;

    const patch = (data: Record<string, string>) =>
        router.patch(url, data, {
            preserveScroll: true,
            onError: (e) => setError(Object.values(e)[0]),
            onSuccess: () => {
                setEditing(false);
                setError(undefined);
            },
        });

    const iconBtn = 'flex h-7 w-7 items-center justify-center rounded-md text-muted hover:bg-gray-100 hover:text-ink';
    const isOwner = fact.source_type === 'owner';

    return (
        <li className={`group px-5 py-3 ${fact.status === 'rejected' ? 'bg-gray-50/70' : ''}`}>
            <div className="flex items-start gap-3">
                <div className="w-28 shrink-0 pt-0.5 text-xs font-medium text-muted">{fact.label}</div>
                <div className="min-w-0 flex-1">
                    {editing ? (
                        <form
                            onSubmit={(e: FormEvent) => {
                                e.preventDefault();
                                patch({ value });
                            }}
                            className="space-y-2"
                        >
                            <Textarea value={value} onChange={(e) => setValue(e.target.value)} rows={fact.key === 'description' || fact.key === 'opening_hours' ? 3 : 1} error={error} autoFocus />
                            <div className="flex gap-2">
                                <Button size="sm" type="submit">
                                    Save
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => { setEditing(false); setValue(fact.value); }}>
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <>
                            <p className={`text-sm break-words whitespace-pre-line ${fact.status === 'rejected' ? 'text-muted line-through' : 'text-ink'}`}>{fact.value}</p>
                            <p className="mt-0.5 text-xs text-muted">
                                {fact.source_label}
                                {inConflict && fact.status !== 'rejected' && <span className="ml-1.5 text-amber-700">· conflicting</span>}
                            </p>
                        </>
                    )}
                </div>
                {!editing && (
                    <div className="flex shrink-0 items-center gap-1">
                        <FactStatusBadge status={fact.status} />
                        {!isOwner && (
                            <div className="ml-1 flex items-center opacity-100 sm:opacity-60 sm:group-hover:opacity-100">
                                {fact.status !== 'confirmed' && fact.status !== 'rejected' && (
                                    <Tooltip label="Confirm">
                                        <button className={`${iconBtn} hover:!text-emerald-600`} onClick={() => patch({ status: 'confirmed' })} aria-label="Confirm">
                                            <Check className="h-4 w-4" />
                                        </button>
                                    </Tooltip>
                                )}
                                {fact.status !== 'rejected' ? (
                                    <Tooltip label="Reject">
                                        <button className={`${iconBtn} hover:!text-red-600`} onClick={() => patch({ status: 'rejected' })} aria-label="Reject">
                                            <X className="h-4 w-4" />
                                        </button>
                                    </Tooltip>
                                ) : (
                                    <Tooltip label="Restore">
                                        <button className={iconBtn} onClick={() => patch({ status: 'extracted' })} aria-label="Restore">
                                            <Undo2 className="h-4 w-4" />
                                        </button>
                                    </Tooltip>
                                )}
                                <Tooltip label="Edit">
                                    <button className={iconBtn} onClick={() => setEditing(true)} aria-label="Edit">
                                        <Pencil className="h-3.5 w-3.5" />
                                    </button>
                                </Tooltip>
                                {fact.source_type === 'manual' && (
                                    <Tooltip label="Delete">
                                        <button className={`${iconBtn} hover:!text-red-600`} onClick={() => router.delete(`/businesses/${businessId}/facts/${fact.id}`, { preserveScroll: true })} aria-label="Delete">
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </Tooltip>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </li>
    );
}

export function AddFactDialog({ open, onClose, businessId, factKeys, initialKey }: { open: boolean; onClose: () => void; businessId: number; factKeys: { key: string; label: string }[]; initialKey?: string }) {
    const [key, setKey] = useState(initialKey ?? 'phone');
    const [value, setValue] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [lastInitial, setLastInitial] = useState(initialKey);
    if (initialKey !== lastInitial) {
        setLastInitial(initialKey);
        if (initialKey) setKey(initialKey);
    }

    const submit = (e?: FormEvent) => {
        e?.preventDefault();
        setBusy(true);
        router.post(`/businesses/${businessId}/facts`, { key, value }, {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: () => {
                setValue('');
                setErrors({});
                onClose();
            },
            onFinish: () => setBusy(false),
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Add information"
            description="Information you add yourself is confirmed automatically."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={() => submit()} loading={busy} disabled={!value.trim()}>
                        Add
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <Select label="Type" value={key} onChange={(e) => setKey(e.target.value)} options={factKeys.map((k) => ({ value: k.key, label: k.label }))} error={errors.key} />
                <Textarea label="Value" value={value} onChange={(e) => setValue(e.target.value)} rows={key === 'description' || key === 'opening_hours' ? 4 : 2} error={errors.value} autoFocus
                    placeholder={key === 'opening_hours' ? 'Mon–Sat 10:00–20:00, Sunday closed' : key === 'service' ? 'One service, e.g. Bridal makeup' : ''} />
            </form>
        </Modal>
    );
}

export function FactsPanel({ facts, businessId, conflictKeys, onAdd }: { facts: FactItem[]; businessId: number; conflictKeys: string[]; onAdd: () => void }) {
    const [filter, setFilter] = useState<Filter>('all');
    const counts = useMemo(
        () => ({
            all: facts.filter((f) => f.status !== 'rejected').length,
            review: facts.filter((f) => f.status === 'extracted' || f.status === 'needs_review').length,
            confirmed: facts.filter((f) => f.status === 'confirmed').length,
            rejected: facts.filter((f) => f.status === 'rejected').length,
        }),
        [facts],
    );

    const visible = facts.filter((f) =>
        filter === 'all' ? f.status !== 'rejected' : filter === 'review' ? f.status === 'extracted' || f.status === 'needs_review' : f.status === filter,
    );
    const unconflictedReview = facts.filter((f) => f.status === 'extracted' && !conflictKeys.includes(f.key)).length;

    return (
        <section>
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-[15px] font-semibold text-ink">Business facts</h2>
                    <p className="mt-0.5 text-sm text-muted">Only confirmed facts are used on your website.</p>
                </div>
                <div className="flex gap-2">
                    {unconflictedReview > 0 && (
                        <Button size="sm" variant="secondary" icon={<CheckCheck className="h-3.5 w-3.5" />} onClick={() => router.post(`/businesses/${businessId}/facts/confirm-all`, {}, { preserveScroll: true })}>
                            Confirm all ({unconflictedReview})
                        </Button>
                    )}
                    <Button size="sm" variant="secondary" icon={<Plus className="h-3.5 w-3.5" />} onClick={onAdd}>
                        Add fact
                    </Button>
                </div>
            </div>
            <Tabs
                className="mb-4"
                value={filter}
                onChange={setFilter}
                items={[
                    { value: 'all', label: 'All', count: counts.all },
                    { value: 'review', label: 'To review', count: counts.review },
                    { value: 'confirmed', label: 'Confirmed', count: counts.confirmed },
                    { value: 'rejected', label: 'Rejected', count: counts.rejected },
                ]}
            />
            <div className="space-y-4">
                {GROUPS.map((group) => {
                    const items = visible.filter((f) => group.keys.includes(f.key));
                    if (items.length === 0) return null;
                    return (
                        <Card key={group.title} padded={false}>
                            <h3 className="border-b border-line px-5 py-3 text-[13px] font-semibold text-ink">{group.title}</h3>
                            <ul className="divide-y divide-line">
                                {items.map((f) => (
                                    <FactRow key={`${f.id}-${f.status}-${f.value}`} fact={f} businessId={businessId} inConflict={conflictKeys.includes(f.key)} />
                                ))}
                            </ul>
                        </Card>
                    );
                })}
                {visible.length === 0 && (
                    <p className="rounded-[12px] border border-dashed border-gray-300 bg-white px-5 py-8 text-center text-sm text-muted">
                        {filter === 'all' ? 'No facts yet. They appear here as your sources are read.' : 'Nothing here.'}
                    </p>
                )}
            </div>
        </section>
    );
}
