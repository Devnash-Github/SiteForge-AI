import { Check } from 'lucide-react';
import type { Theme } from '@/types';
import { FONT_STACKS } from '@/website-renderer/theme';
import type { Studio } from './useStudio';

const PRESET_LABELS: Record<string, string> = { modern: 'Modern', warm: 'Warm', fresh: 'Fresh' };
const COLOR_FIELDS: { key: 'primary' | 'secondary' | 'background' | 'text'; label: string; hint: string }[] = [
    { key: 'primary', label: 'Primary', hint: 'Buttons and accents' },
    { key: 'secondary', label: 'Secondary', hint: 'Soft section backgrounds' },
    { key: 'background', label: 'Background', hint: 'Page background' },
    { key: 'text', label: 'Text', hint: 'Body text and footer' },
];

function ColorField({ label, hint, value, onChange }: { label: string; hint: string; value: string; onChange: (v: string) => void }) {
    const valid = /^#[0-9a-fA-F]{6}$/.test(value);
    return (
        <div className="flex items-center gap-3">
            <label className="relative h-9 w-9 shrink-0 cursor-pointer overflow-hidden rounded-[8px] border border-line shadow-sm" style={{ background: valid ? value : '#fff' }}>
                <input type="color" value={valid ? value : '#000000'} onChange={(e) => onChange(e.target.value.toUpperCase())} className="absolute inset-0 cursor-pointer opacity-0" aria-label={`${label} color`} />
            </label>
            <div className="min-w-0 flex-1">
                <p className="text-[13px] font-medium text-ink">{label}</p>
                <p className="text-xs text-muted">{hint}</p>
            </div>
            <input
                value={value}
                onChange={(e) => onChange(e.target.value.trim())}
                maxLength={7}
                className={`h-8 w-[84px] rounded-[8px] border px-2 font-mono text-xs uppercase focus:ring-2 focus:ring-accent/25 focus:outline-none ${valid ? 'border-line' : 'border-red-400'}`}
                aria-label={`${label} hex value`}
            />
        </div>
    );
}

export function DesignPanel({ studio, presets }: { studio: Studio; presets: Record<string, Theme> }) {
    const theme = studio.data.theme;
    const set = (patch: Partial<Theme>) => {
        const next = { ...theme, ...patch, preset: 'custom' };
        // Only persist once every colour is a complete hex value.
        const ok = ['primary', 'secondary', 'background', 'text'].every((k) => /^#[0-9a-fA-F]{6}$/.test(next[k as keyof Theme] as string));
        if (ok) studio.updateTheme(next);
        else studio.replaceLocal((d) => ({ ...d, theme: next }));
    };

    return (
        <div className="h-full space-y-7 overflow-y-auto px-4 py-5">
            <div>
                <p className="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">Theme presets</p>
                <div className="grid grid-cols-3 gap-2">
                    {Object.entries(presets).map(([key, preset]) => {
                        const active = theme.preset === key;
                        return (
                            <button
                                key={key}
                                onClick={() => studio.updateTheme(preset)}
                                className={`relative rounded-[10px] border p-2.5 text-left transition ${active ? 'border-accent ring-2 ring-accent/20' : 'border-line hover:border-gray-300'}`}
                            >
                                <div className="mb-2 flex h-10 overflow-hidden rounded-[6px]" style={{ background: preset.background }}>
                                    <div className="w-1/2" style={{ background: preset.secondary }} />
                                    <div className="flex w-1/2 items-center justify-center">
                                        <span className="h-3 w-8" style={{ background: preset.primary, borderRadius: preset.button_style === 'pill' ? 99 : preset.button_style === 'square' ? 1 : 4 }} />
                                    </div>
                                </div>
                                <p className="text-xs font-medium text-ink" style={{ fontFamily: FONT_STACKS[preset.font].heading }}>
                                    {PRESET_LABELS[key] ?? key}
                                </p>
                                {active && <Check className="absolute top-1.5 right-1.5 h-3.5 w-3.5 text-accent" />}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="space-y-3.5">
                <p className="text-xs font-semibold tracking-wide text-muted uppercase">Colors</p>
                {COLOR_FIELDS.map((f) => (
                    <ColorField key={f.key} label={f.label} hint={f.hint} value={theme[f.key]} onChange={(v) => set({ [f.key]: v })} />
                ))}
            </div>

            <div>
                <p className="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">Font</p>
                <div className="space-y-2">
                    {(Object.keys(FONT_STACKS) as Theme['font'][]).map((font) => (
                        <button
                            key={font}
                            onClick={() => set({ font })}
                            className={`flex w-full items-center justify-between rounded-[10px] border px-3 py-2.5 text-left ${theme.font === font ? 'border-accent bg-accent-soft/40' : 'border-line hover:bg-gray-50'}`}
                        >
                            <span className="text-[15px] text-ink" style={{ fontFamily: FONT_STACKS[font].heading }}>
                                {FONT_STACKS[font].label}
                            </span>
                            {theme.font === font && <Check className="h-4 w-4 text-accent" />}
                        </button>
                    ))}
                </div>
            </div>

            <div>
                <p className="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">Button style</p>
                <div className="grid grid-cols-3 gap-2">
                    {(['rounded', 'pill', 'square'] as const).map((style) => (
                        <button
                            key={style}
                            onClick={() => set({ button_style: style })}
                            className={`flex flex-col items-center gap-2 rounded-[10px] border py-3 text-xs font-medium capitalize ${theme.button_style === style ? 'border-accent bg-accent-soft/40 text-ink' : 'border-line text-muted hover:bg-gray-50'}`}
                        >
                            <span className="h-5 w-14" style={{ background: theme.primary, borderRadius: style === 'pill' ? 99 : style === 'square' ? 2 : 6 }} />
                            {style}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
