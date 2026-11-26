import { forwardRef, useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

const control =
    'block w-full rounded-[10px] border bg-white px-3 text-sm text-ink placeholder:text-gray-400 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-accent/25 disabled:bg-gray-50 disabled:text-muted';

function borderFor(error?: string) {
    return error ? 'border-red-400 focus:border-red-500' : 'border-line focus:border-accent';
}

interface FieldProps {
    label?: ReactNode;
    hint?: ReactNode;
    error?: string;
    required?: boolean;
    className?: string;
    children: (id: string) => ReactNode;
}

export function Field({ label, hint, error, required, className, children }: FieldProps) {
    const id = useId();
    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <label htmlFor={id} className="block text-[13px] font-medium text-ink">
                    {label}
                    {required && <span className="ml-0.5 text-red-500">*</span>}
                </label>
            )}
            {children(id)}
            {error ? <FieldError message={error} /> : hint ? <p className="text-xs text-muted">{hint}</p> : null}
        </div>
    );
}

export function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return (
        <p className="text-xs font-medium text-red-600" role="alert">
            {message}
        </p>
    );
}

type InputProps = InputHTMLAttributes<HTMLInputElement> & { label?: ReactNode; hint?: ReactNode; error?: string; icon?: ReactNode; wrapperClassName?: string };

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input({ label, hint, error, icon, className, wrapperClassName, required, ...rest }, ref) {
    return (
        <Field label={label} hint={hint} error={error} required={required} className={wrapperClassName}>
            {(id) => (
                <div className="relative">
                    {icon && <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">{icon}</span>}
                    <input
                        ref={ref}
                        id={id}
                        required={required}
                        aria-invalid={!!error}
                        className={cn(control, borderFor(error), 'h-10', icon ? 'pl-9' : '', className)}
                        {...rest}
                    />
                </div>
            )}
        </Field>
    );
});

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & { label?: ReactNode; hint?: ReactNode; error?: string; wrapperClassName?: string };

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea({ label, hint, error, className, wrapperClassName, rows = 4, required, ...rest }, ref) {
    return (
        <Field label={label} hint={hint} error={error} required={required} className={wrapperClassName}>
            {(id) => (
                <textarea
                    ref={ref}
                    id={id}
                    rows={rows}
                    required={required}
                    aria-invalid={!!error}
                    className={cn(control, borderFor(error), 'resize-y py-2.5 leading-relaxed', className)}
                    {...rest}
                />
            )}
        </Field>
    );
});

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
    label?: ReactNode;
    hint?: ReactNode;
    error?: string;
    options: { value: string; label: string }[];
    wrapperClassName?: string;
};

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select({ label, hint, error, options, className, wrapperClassName, ...rest }, ref) {
    return (
        <Field label={label} hint={hint} error={error} className={wrapperClassName}>
            {(id) => (
                <div className="relative">
                    <select ref={ref} id={id} className={cn(control, borderFor(error), 'h-10 appearance-none pr-9', className)} {...rest}>
                        {options.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <ChevronDown className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                </div>
            )}
        </Field>
    );
});

export function Checkbox({ label, checked, onChange, description }: { label: ReactNode; checked: boolean; onChange: (v: boolean) => void; description?: ReactNode }) {
    const id = useId();
    return (
        <label htmlFor={id} className="flex cursor-pointer items-start gap-2.5">
            <input id={id} type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-line accent-[#635BFF]" />
            <span>
                <span className="block text-sm text-ink">{label}</span>
                {description && <span className="block text-xs text-muted">{description}</span>}
            </span>
        </label>
    );
}
