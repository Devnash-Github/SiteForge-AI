import { useState } from 'react';
import { Check, ImageOff } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import type { MediaItem } from '@/types';

interface Props {
    open: boolean;
    onClose: () => void;
    media: MediaItem[];
    selected: number[];
    multiple?: boolean;
    onSelect: (ids: number[]) => void;
    onUploadClick?: () => void;
}

export function ImagePicker({ open, onClose, media, selected, multiple = false, onSelect, onUploadClick }: Props) {
    const [picked, setPicked] = useState<number[]>(selected);
    const [wasOpen, setWasOpen] = useState(open);
    if (open !== wasOpen) {
        setWasOpen(open);
        if (open) setPicked(selected);
    }

    const toggle = (id: number) => {
        if (!multiple) return setPicked(picked[0] === id ? [] : [id]);
        setPicked(picked.includes(id) ? picked.filter((x) => x !== id) : [...picked, id]);
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={multiple ? 'Choose images' : 'Choose an image'}
            description={multiple ? 'Select the photos to show, in the order you click them.' : undefined}
            footer={
                <>
                    {onUploadClick && (
                        <Button variant="ghost" className="mr-auto" onClick={onUploadClick}>
                            Upload new images
                        </Button>
                    )}
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        onClick={() => {
                            onSelect(picked);
                            onClose();
                        }}
                    >
                        {multiple ? `Use ${picked.length} image${picked.length === 1 ? '' : 's'}` : picked.length ? 'Use image' : 'Remove image'}
                    </Button>
                </>
            }
        >
            {media.length === 0 ? (
                <div className="flex flex-col items-center py-10 text-center text-sm text-muted">
                    <ImageOff className="mb-3 h-6 w-6" />
                    No images uploaded yet. Upload photos in the Assets tab.
                </div>
            ) : (
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                    {media.map((m) => {
                        const index = picked.indexOf(m.id);
                        const on = index >= 0;
                        return (
                            <button
                                key={m.id}
                                type="button"
                                onClick={() => toggle(m.id)}
                                className={`relative aspect-square overflow-hidden rounded-[10px] border-2 transition ${on ? 'border-accent ring-2 ring-accent/25' : 'border-transparent hover:border-gray-300'}`}
                                aria-pressed={on}
                                aria-label={m.alt_text || m.file_name}
                            >
                                <img src={m.url} alt={m.alt_text ?? ''} className="h-full w-full object-cover" />
                                {on && (
                                    <span className="absolute top-1.5 right-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-accent text-[11px] font-semibold text-white">
                                        {multiple ? index + 1 : <Check className="h-3.5 w-3.5" />}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
        </Modal>
    );
}
