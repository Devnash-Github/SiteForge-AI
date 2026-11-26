import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import { CheckCircle2, Info, X, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

type ToastKind = 'success' | 'error' | 'info';
interface ToastItem {
    id: number;
    kind: ToastKind;
    message: string;
}

const ToastContext = createContext<(message: string, kind?: ToastKind) => void>(() => {});

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    const dismiss = useCallback((id: number) => setToasts((t) => t.filter((x) => x.id !== id)), []);

    const push = useCallback(
        (message: string, kind: ToastKind = 'success') => {
            const id = Date.now() + Math.random();
            setToasts((t) => [...t.filter((x) => x.message !== message).slice(-3), { id, kind, message }]);
            setTimeout(() => dismiss(id), kind === 'error' ? 7000 : 4000);
        },
        [dismiss],
    );

    return (
        <ToastContext.Provider value={push}>
            {children}
            <div className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-full max-w-sm flex-col gap-2" aria-live="polite">
                {toasts.map((t) => (
                    <div key={t.id} className="pointer-events-auto flex animate-slide-up items-start gap-3 rounded-[12px] border border-line bg-white px-4 py-3 shadow-pop">
                        {t.kind === 'success' && <CheckCircle2 className="mt-0.5 h-4.5 w-4.5 shrink-0 text-emerald-600" />}
                        {t.kind === 'error' && <XCircle className="mt-0.5 h-4.5 w-4.5 shrink-0 text-red-600" />}
                        {t.kind === 'info' && <Info className="mt-0.5 h-4.5 w-4.5 shrink-0 text-accent" />}
                        <p className={cn('flex-1 text-sm', t.kind === 'error' ? 'text-red-800' : 'text-ink')}>{t.message}</p>
                        <button onClick={() => dismiss(t.id)} className="text-gray-400 hover:text-ink" aria-label="Dismiss">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast() {
    return useContext(ToastContext);
}
