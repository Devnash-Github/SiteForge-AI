import type { CSSProperties } from 'react';
import type { Theme } from '@/types';

export const FONT_STACKS: Record<Theme['font'], { body: string; heading: string; label: string }> = {
    inter: { body: "'Inter', ui-sans-serif, system-ui, sans-serif", heading: "'Inter', ui-sans-serif, system-ui, sans-serif", label: 'Inter' },
    'dm-sans': { body: "'DM Sans', ui-sans-serif, system-ui, sans-serif", heading: "'DM Sans', ui-sans-serif, system-ui, sans-serif", label: 'DM Sans' },
    playfair: { body: "'Inter', ui-sans-serif, system-ui, sans-serif", heading: "'Playfair Display', Georgia, serif", label: 'Playfair + Inter' },
};

export const BUTTON_RADIUS: Record<Theme['button_style'], string> = {
    rounded: '10px',
    pill: '999px',
    square: '2px',
};

export const DEFAULT_THEME: Theme = {
    preset: 'modern',
    primary: '#4F46E5',
    secondary: '#EEF2FF',
    background: '#FFFFFF',
    text: '#111827',
    font: 'inter',
    button_style: 'rounded',
};

/** Picks black or white text for readable contrast on a given background. */
export function readableOn(hex: string): string {
    const n = parseInt(hex.replace('#', ''), 16);
    if (Number.isNaN(n)) return '#FFFFFF';
    const r = (n >> 16) & 255;
    const g = (n >> 8) & 255;
    const b = n & 255;
    const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
    return luminance > 0.62 ? '#111827' : '#FFFFFF';
}

const HEX = /^#[0-9a-fA-F]{6}$/;
const safe = (value: string | undefined, fallback: string) => (value && HEX.test(value) ? value : fallback);

/** Theme → CSS custom properties consumed by every section component. */
export function themeVariables(theme: Partial<Theme> | undefined): CSSProperties {
    const t = { ...DEFAULT_THEME, ...(theme ?? {}) };
    const fonts = FONT_STACKS[t.font] ?? FONT_STACKS.inter;
    const primary = safe(t.primary, DEFAULT_THEME.primary);
    return {
        '--site-primary': primary,
        '--site-on-primary': readableOn(primary),
        '--site-secondary': safe(t.secondary, DEFAULT_THEME.secondary),
        '--site-bg': safe(t.background, DEFAULT_THEME.background),
        '--site-text': safe(t.text, DEFAULT_THEME.text),
        '--site-font': fonts.body,
        '--site-heading-font': fonts.heading,
        '--site-radius': BUTTON_RADIUS[t.button_style] ?? BUTTON_RADIUS.rounded,
    } as CSSProperties;
}
