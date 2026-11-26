import type { ReactNode } from 'react';

/** Lightweight CSS tooltip (shown on hover and keyboard focus). */
export function Tooltip({ label, children, side = 'top' }: { label: string; children: ReactNode; side?: 'top' | 'bottom' }) {
    return (
        <span className="group/tt relative inline-flex">
            {children}
            <span
                role="tooltip"
                className={`pointer-events-none absolute left-1/2 z-40 -translate-x-1/2 rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium whitespace-nowrap text-white opacity-0 shadow transition-opacity group-focus-within/tt:opacity-100 group-hover/tt:opacity-100 ${side === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5'}`}
            >
                {label}
            </span>
        </span>
    );
}
