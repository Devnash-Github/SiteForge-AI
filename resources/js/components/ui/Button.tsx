import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'subtle';
type Size = 'sm' | 'md' | 'lg' | 'icon';

const variants: Record<Variant, string> = {
    primary: 'bg-accent text-white hover:bg-accent-hover shadow-sm disabled:bg-accent/60',
    secondary: 'bg-white text-ink border border-line hover:bg-gray-50 shadow-sm',
    ghost: 'text-muted hover:text-ink hover:bg-gray-100',
    danger: 'bg-red-600 text-white hover:bg-red-700 shadow-sm',
    subtle: 'bg-accent-soft text-accent hover:bg-[#e4e2ff]',
};

const sizes: Record<Size, string> = {
    sm: 'h-8 px-3 text-[13px] gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-11 px-5 text-[15px] gap-2',
    icon: 'h-8 w-8 justify-center',
};

export function buttonClasses(variant: Variant = 'primary', size: Size = 'md', className?: string) {
    return cn(
        'inline-flex items-center justify-center rounded-[10px] font-medium whitespace-nowrap transition-colors',
        'disabled:cursor-not-allowed disabled:opacity-60',
        variants[variant],
        sizes[size],
        className,
    );
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    icon?: ReactNode;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    { variant = 'primary', size = 'md', loading = false, icon, className, children, disabled, type = 'button', ...rest },
    ref,
) {
    return (
        <button ref={ref} type={type} className={buttonClasses(variant, size, className)} disabled={disabled || loading} {...rest}>
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : icon}
            {children}
        </button>
    );
});

interface LinkButtonProps {
    href: string;
    variant?: Variant;
    size?: Size;
    icon?: ReactNode;
    className?: string;
    children?: ReactNode;
    external?: boolean;
    title?: string;
}

export function LinkButton({ href, variant = 'primary', size = 'md', icon, className, children, external, title }: LinkButtonProps) {
    if (external) {
        return (
            <a href={href} target="_blank" rel="noopener noreferrer" className={buttonClasses(variant, size, className)} title={title}>
                {icon}
                {children}
            </a>
        );
    }
    return (
        <Link href={href} className={buttonClasses(variant, size, className)} title={title}>
            {icon}
            {children}
        </Link>
    );
}
