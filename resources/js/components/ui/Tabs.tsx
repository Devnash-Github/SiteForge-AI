import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface TabsProps<T extends string> {
    value: T;
    onChange: (v: T) => void;
    items: { value: T; label: ReactNode; count?: number }[];
    className?: string;
    variant?: 'underline' | 'pill';
}

export function Tabs<T extends string>({ value, onChange, items, className, variant = 'pill' }: TabsProps<T>) {
    if (variant === 'underline') {
        return (
            <div role="tablist" className={cn('flex gap-5 border-b border-line', className)}>
                {items.map((item) => (
                    <button
                        key={item.value}
                        role="tab"
                        aria-selected={value === item.value}
                        onClick={() => onChange(item.value)}
                        className={cn(
                            '-mb-px border-b-2 pt-1 pb-2.5 text-sm font-medium transition-colors',
                            value === item.value ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink',
                        )}
                    >
                        {item.label}
                        {item.count !== undefined && <span className="ml-1.5 text-xs text-muted">{item.count}</span>}
                    </button>
                ))}
            </div>
        );
    }
    return (
        <div role="tablist" className={cn('inline-flex rounded-[10px] bg-gray-100 p-1', className)}>
            {items.map((item) => (
                <button
                    key={item.value}
                    role="tab"
                    aria-selected={value === item.value}
                    onClick={() => onChange(item.value)}
                    className={cn(
                        'rounded-[8px] px-3 py-1.5 text-[13px] font-medium transition-colors',
                        value === item.value ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink',
                    )}
                >
                    {item.label}
                    {item.count !== undefined && <span className="ml-1.5 text-xs text-muted">{item.count}</span>}
                </button>
            ))}
        </div>
    );
}
