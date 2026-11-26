import { useCallback, useEffect, useRef, useState } from 'react';
import { errorMessage, fieldErrors, http } from '@/lib/http';
import { useToast } from '@/components/ui/Toast';
import type { RendererData, Section, Theme } from '@/types';

export type SaveState = 'saved' | 'saving' | 'error';

interface ServerPayload {
    renderer: RendererData;
    has_unpublished_changes: boolean;
    saved_at: string;
}

/**
 * Editor state with optimistic updates and debounced autosave. Every change
 * updates the preview immediately; the server validates and persists it.
 */
export function useStudio(websiteId: number, initial: RendererData, initialUnpublished: boolean) {
    const toast = useToast();
    const [data, setData] = useState<RendererData>(initial);
    const [saveState, setSaveState] = useState<SaveState>('saved');
    const [lastError, setLastError] = useState<string | null>(null);
    const [sectionErrors, setSectionErrors] = useState<Record<number, Record<string, string>>>({});
    const [unpublished, setUnpublished] = useState(initialUnpublished);

    const tasks = useRef(new Map<string, () => Promise<unknown>>());
    const failed = useRef(new Map<string, () => Promise<unknown>>());
    const timers = useRef(new Map<string, ReturnType<typeof setTimeout>>());
    const inflight = useRef(0);

    const base = `/websites/${websiteId}`;

    const run = useCallback(
        async (key: string) => {
            const task = tasks.current.get(key);
            if (!task) return;
            tasks.current.delete(key);
            timers.current.delete(key);
            inflight.current++;
            setSaveState('saving');
            try {
                await task();
                failed.current.delete(key);
                if (key.startsWith('section-')) {
                    const id = Number(key.slice(8));
                    setSectionErrors((e) => {
                        const next = { ...e };
                        delete next[id];
                        return next;
                    });
                }
            } catch (error) {
                failed.current.set(key, task);
                const msg = errorMessage(error, 'Your changes could not be saved.');
                setLastError(msg);
                if (key.startsWith('section-')) {
                    const errs = fieldErrors(error);
                    const id = Number(key.slice(8));
                    const mapped = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k.replace(/^content\./, ''), v]));
                    setSectionErrors((e) => ({ ...e, [id]: mapped }));
                }
                toast(msg, 'error');
            } finally {
                inflight.current--;
                if (inflight.current === 0) {
                    setSaveState(failed.current.size > 0 ? 'error' : tasks.current.size > 0 ? 'saving' : 'saved');
                }
            }
        },
        [toast],
    );

    const schedule = useCallback(
        (key: string, task: () => Promise<unknown>, delay = 700) => {
            tasks.current.set(key, task);
            failed.current.delete(key);
            const existing = timers.current.get(key);
            if (existing) clearTimeout(existing);
            setSaveState('saving');
            timers.current.set(key, setTimeout(() => run(key), delay));
        },
        [run],
    );

    /** Save everything now (before preview, publish or structural changes). */
    const flush = useCallback(async () => {
        const keys = [...tasks.current.keys()];
        keys.forEach((k) => {
            const t = timers.current.get(k);
            if (t) clearTimeout(t);
        });
        await Promise.all(keys.map((k) => run(k)));
        while (inflight.current > 0) await new Promise((r) => setTimeout(r, 50));
        return failed.current.size === 0;
    }, [run]);

    const retry = useCallback(() => {
        for (const [key, task] of failed.current) schedule(key, task, 0);
    }, [schedule]);

    const applyServer = (payload: ServerPayload) => {
        setUnpublished(payload.has_unpublished_changes);
    };

    useEffect(() => {
        const warn = (e: BeforeUnloadEvent) => {
            if (tasks.current.size > 0 || inflight.current > 0 || failed.current.size > 0) {
                e.preventDefault();
            }
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, []);

    const updateSection = useCallback(
        <T extends Section>(id: number, content: T['content']) => {
            setData((d) => ({ ...d, sections: d.sections.map((s) => (s.id === id ? ({ ...s, content } as Section) : s)) }));
            schedule(`section-${id}`, () => http.put<ServerPayload>(`${base}/sections/${id}`, { content }).then((r) => applyServer(r.data)));
        },
        [schedule, base],
    );

    const setVisible = useCallback(
        (id: number, visible: boolean) => {
            setData((d) => ({ ...d, sections: d.sections.map((s) => (s.id === id ? { ...s, visible } : s)) }));
            schedule(`visible-${id}`, () => http.put<ServerPayload>(`${base}/sections/${id}`, { is_visible: visible }).then((r) => applyServer(r.data)), 0);
        },
        [schedule, base],
    );

    const move = useCallback(
        (id: number, direction: -1 | 1) => {
            setData((d) => {
                const list = [...d.sections];
                const i = list.findIndex((s) => s.id === id);
                const j = i + direction;
                if (i < 0 || j < 0 || j >= list.length) return d;
                [list[i], list[j]] = [list[j], list[i]];
                const order = list.map((s) => s.id);
                schedule('order', () => http.post<ServerPayload>(`${base}/sections/reorder`, { order }).then((r) => applyServer(r.data)), 300);
                return { ...d, sections: list };
            });
        },
        [schedule, base],
    );

    const structural = useCallback(
        async (request: () => Promise<{ data: ServerPayload }>) => {
            const ok = await flush();
            if (!ok) {
                toast('Save your pending changes first (use Retry in the toolbar).', 'error');
                return false;
            }
            setSaveState('saving');
            try {
                const r = await request();
                setData((d) => ({ ...r.data.renderer, assets: d.assets }));
                applyServer(r.data);
                setSaveState('saved');
                return true;
            } catch (error) {
                setSaveState('saved');
                toast(errorMessage(error), 'error');
                return false;
            }
        },
        [flush, toast],
    );

    const addSection = useCallback((type: string) => structural(() => http.post<ServerPayload>(`${base}/sections`, { type })), [structural, base]);
    const removeSection = useCallback((id: number) => structural(() => http.delete<ServerPayload>(`${base}/sections/${id}`)), [structural, base]);

    const updateTheme = useCallback(
        (theme: Theme) => {
            setData((d) => ({ ...d, theme }));
            schedule('theme', () => http.put<ServerPayload>(`${base}/theme`, { theme }).then((r) => applyServer(r.data)), 500);
        },
        [schedule, base],
    );

    /** Local-only update used after server-side changes such as image deletion. */
    const replaceLocal = useCallback((updater: (d: RendererData) => RendererData) => setData(updater), []);

    return {
        data,
        saveState,
        lastError,
        sectionErrors,
        unpublished,
        setUnpublished,
        updateSection,
        setVisible,
        move,
        addSection,
        removeSection,
        updateTheme,
        flush,
        retry,
        replaceLocal,
    };
}

export type Studio = ReturnType<typeof useStudio>;
