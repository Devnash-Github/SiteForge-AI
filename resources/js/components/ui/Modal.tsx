import { useEffect, useRef, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from './Button';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    dismissible?: boolean;
}

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

/** Accessible dialog: focus moves inside, Escape and backdrop click close it. */
export function Modal({ open, onClose, title, description, children, footer, size = 'md', dismissible = true }: ModalProps) {
    const panel = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const previous = document.activeElement as HTMLElement | null;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && dismissible) onClose();
        };
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        const t = setTimeout(() => {
            const focusable = panel.current?.querySelector<HTMLElement>('input, textarea, select, button[data-autofocus], button:not([aria-label="Close"])');
            (focusable ?? panel.current)?.focus();
        }, 20);
        return () => {
            clearTimeout(t);
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
            previous?.focus?.();
        };
    }, [open, dismissible, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 animate-fade-in bg-gray-900/40" onClick={dismissible ? onClose : undefined} />
            <div
                ref={panel}
                role="dialog"
                aria-modal="true"
                tabIndex={-1}
                className={cn('relative flex max-h-[90vh] w-full animate-slide-up flex-col rounded-[14px] bg-white shadow-pop outline-none', widths[size])}
            >
                {(title || dismissible) && (
                    <div className="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                        <div>
                            {title && <h2 className="text-base font-semibold text-ink">{title}</h2>}
                            {description && <p className="mt-1 text-sm text-muted">{description}</p>}
                        </div>
                        {dismissible && (
                            <button onClick={onClose} aria-label="Close" className="-mr-2 rounded-lg p-1.5 text-muted hover:bg-gray-100 hover:text-ink">
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                )}
                <div className="overflow-y-auto px-6 py-5">{children}</div>
                {footer && <div className="flex items-center justify-end gap-2 border-t border-line bg-gray-50/60 px-6 py-3.5 rounded-b-[14px]">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}

interface ConfirmProps {
    open: boolean;
    title: ReactNode;
    message: ReactNode;
    confirmLabel?: string;
    danger?: boolean;
    loading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export function ConfirmDialog({ open, title, message, confirmLabel = 'Confirm', danger = false, loading, onConfirm, onCancel }: ConfirmProps) {
    return (
        <Modal
            open={open}
            onClose={onCancel}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onCancel} disabled={loading}>
                        Cancel
                    </Button>
                    <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} loading={loading} data-autofocus>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <div className="flex gap-3.5">
                {danger && (
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-50">
                        <AlertTriangle className="h-4.5 w-4.5 text-red-600" />
                    </div>
                )}
                <div>
                    <h3 className="text-base font-semibold text-ink">{title}</h3>
                    <div className="mt-1.5 text-sm text-muted">{message}</div>
                </div>
            </div>
        </Modal>
    );
}
