import { useRef, useState, type DragEvent } from 'react';
import { FileText, Image as ImageIcon, UploadCloud, X } from 'lucide-react';
import { cn, formatBytes } from '@/lib/utils';
import { ProgressBar } from './States';

interface FileUploaderProps {
    files: File[];
    onChange: (files: File[]) => void;
    accept?: string[];
    maxSizeMb: number;
    maxFiles?: number;
    progress?: number | null;
    error?: string;
    fileErrors?: Record<number, string>;
    hint?: string;
    compact?: boolean;
}

const DEFAULT_ACCEPT = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
const EXT: Record<string, string> = { 'application/pdf': 'PDF', 'image/jpeg': 'JPG', 'image/png': 'PNG', 'image/webp': 'WebP' };

/** Drag-and-drop uploader with client-side type/size checks (the server validates again). */
export function FileUploader({ files, onChange, accept = DEFAULT_ACCEPT, maxSizeMb, maxFiles = 20, progress, error, fileErrors = {}, hint, compact }: FileUploaderProps) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [rejected, setRejected] = useState<string[]>([]);

    const add = (list: FileList | null) => {
        if (!list) return;
        const next = [...files];
        const problems: string[] = [];
        for (const file of Array.from(list)) {
            const type = file.type || (file.name.toLowerCase().endsWith('.pdf') ? 'application/pdf' : '');
            if (!accept.includes(type)) {
                problems.push(`${file.name}: unsupported type (use ${accept.map((a) => EXT[a]).join(', ')}).`);
            } else if (file.size > maxSizeMb * 1024 * 1024) {
                problems.push(`${file.name}: larger than ${maxSizeMb} MB.`);
            } else if (next.length >= maxFiles) {
                problems.push(`${file.name}: you can add up to ${maxFiles} files.`);
            } else if (!next.some((f) => f.name === file.name && f.size === file.size)) {
                next.push(file);
            }
        }
        setRejected(problems);
        onChange(next);
    };

    const onDrop = (e: DragEvent) => {
        e.preventDefault();
        setDragging(false);
        add(e.dataTransfer.files);
    };

    return (
        <div className="space-y-3">
            <div
                role="button"
                tabIndex={0}
                onClick={() => input.current?.click()}
                onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && input.current?.click()}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
                className={cn(
                    'flex cursor-pointer flex-col items-center justify-center rounded-[12px] border border-dashed text-center transition-colors',
                    compact ? 'px-4 py-5' : 'px-6 py-8',
                    dragging ? 'border-accent bg-accent-soft/60' : error ? 'border-red-300 bg-red-50/40' : 'border-gray-300 bg-gray-50/60 hover:border-accent/60 hover:bg-accent-soft/30',
                )}
            >
                <UploadCloud className={cn('mb-2 text-accent', compact ? 'h-5 w-5' : 'h-7 w-7')} />
                <p className="text-sm font-medium text-ink">
                    Drag files here or <span className="text-accent">browse</span>
                </p>
                <p className="mt-1 text-xs text-muted">{hint ?? `${accept.map((a) => EXT[a]).join(', ')} · up to ${maxSizeMb} MB each`}</p>
                <input
                    ref={input}
                    type="file"
                    multiple
                    accept={accept.join(',')}
                    className="hidden"
                    onChange={(e) => {
                        add(e.target.files);
                        e.target.value = '';
                    }}
                />
            </div>

            {(rejected.length > 0 || error) && (
                <div className="space-y-1" role="alert">
                    {error && <p className="text-xs font-medium text-red-600">{error}</p>}
                    {rejected.map((r) => (
                        <p key={r} className="text-xs text-red-600">
                            {r}
                        </p>
                    ))}
                </div>
            )}

            {files.length > 0 && (
                <ul className="divide-y divide-line rounded-[12px] border border-line bg-white">
                    {files.map((file, i) => (
                        <li key={file.name + file.size} className="flex items-center gap-3 px-3 py-2.5">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-muted">
                                {file.type === 'application/pdf' ? <FileText className="h-4 w-4" /> : <ImageIcon className="h-4 w-4" />}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm text-ink">{file.name}</p>
                                {fileErrors[i] ? <p className="text-xs text-red-600">{fileErrors[i]}</p> : <p className="text-xs text-muted">{formatBytes(file.size)}</p>}
                            </div>
                            {progress == null && (
                                <button
                                    type="button"
                                    onClick={() => onChange(files.filter((_, j) => j !== i))}
                                    className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-ink"
                                    aria-label={`Remove ${file.name}`}
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {progress != null && (
                <div className="space-y-1.5">
                    <div className="flex justify-between text-xs text-muted">
                        <span>Uploading…</span>
                        <span>{Math.round(progress)}%</span>
                    </div>
                    <ProgressBar value={progress} />
                </div>
            )}
        </div>
    );
}
