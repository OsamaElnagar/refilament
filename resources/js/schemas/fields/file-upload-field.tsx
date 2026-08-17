import { Loader2, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';

import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';
import { cn } from '@/lib/utils';
import type { FieldProps } from '@/schemas/registry';

interface UploadedFile {
    path: string;
    url: string;
    name: string;
    size: number;
    type: string;
}

/**
 * Path → URL cache across renders: the upload response carries the real
 * storage URL, but the form value holds only paths, so a re-render (or a
 * reload) resolves known paths from this map and falls back to the standard
 * public-disk mapping (`/storage/{path}`) otherwise.
 */
const urlsByPath = new Map<string, string>();

function resolveUrl(path: string): string {
    const known = urlsByPath.get(path);

    if (known) {
        return known;
    }

    const clean = path.replace(/^\/+/, '');

    return `/${clean.startsWith('storage/') ? clean : `storage/${clean}`}`;
}

/**
 * File upload field (mirrors Filament's FileUpload) — the value is a stored
 * path (or an array of paths when `multiple`). Picking a file immediately
 * POSTs it to the panel's typed upload endpoint and stores the returned
 * path; the submit payload carries paths, never blobs. Image types render a
 * thumbnail preview when `imagePreview` is on.
 */
export default function FileUploadField({ node, value, error, onChange, disabled }: FieldProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const multiple = node.multiple === true;
    const preview = node.imagePreview !== false;
    const disk = typeof node.disk === 'string' ? node.disk : 'public';
    const directory = typeof node.directory === 'string' ? node.directory : '';
    const accept = Array.isArray(node.acceptedFileTypes) ? node.acceptedFileTypes.join(',') : undefined;
    const maxSize = typeof node.maxSize === 'number' ? node.maxSize : undefined;

    const files: UploadedFile[] = [];
    const raw = Array.isArray(value) ? value : value ? [value] : [];

    for (const entry of raw) {
        if (typeof entry === 'string') {
            files.push({ path: entry, url: resolveUrl(entry), name: entry.split('/').pop() ?? entry, size: 0, type: '' });
        } else if (entry && typeof entry === 'object' && 'url' in (entry as object)) {
            files.push(entry as UploadedFile);
        }
    }

    const uploadFile = async (file: File): Promise<void> => {
        setUploading(true);
        setUploadError(null);

        const form = new FormData();
        form.append('file', file);
        form.append('disk', disk);
        if (directory) {
            form.append('directory', directory);
        }

        const headers: Record<string, string> = { Accept: 'application/json' };
        const csrfToken = readCsrfToken();

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        try {
            const response = await fetch(panelUrl('/upload'), { method: 'POST', headers, body: form });

            if (!response.ok) {
                const payload = (await response.json().catch(() => null)) as { error?: string; errors?: Record<string, string[]> } | null;
                const message = payload?.error ?? payload?.errors?.file?.[0] ?? 'Upload failed.';
                throw new Error(message);
            }

            const stored = (await response.json()) as UploadedFile;
            urlsByPath.set(stored.path, stored.url);
            const next = multiple ? [...files, stored] : [stored];

            onChange?.(multiple ? next.map((f) => f.path) : stored.path);
        } catch (error) {
            setUploadError(error instanceof Error ? error.message : 'Upload failed.');
        } finally {
            setUploading(false);
            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    };

    const removeFile = (index: number): void => {
        const next = files.filter((_, i) => i !== index);
        onChange?.(multiple ? next.map((f) => f.path) : null);
    };

    const isImage = (file: UploadedFile): boolean => file.type.startsWith('image/') || /\.(png|jpe?g|gif|webp|svg|avif)$/i.test(file.path);

    return (
        <div className="space-y-2">
            <label className="text-sm font-medium leading-none text-foreground">
                {node.label ?? node.name}
                {node.required ? <span className="ml-0.5 text-destructive">*</span> : null}
            </label>

            <div
                className={cn(
                    'flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-border px-4 py-6 text-sm text-muted-foreground transition hover:border-primary/50 hover:text-foreground',
                    disabled && 'cursor-not-allowed opacity-50',
                )}
                onClick={() => !disabled && inputRef.current?.click()}
            >
                {uploading ? <Loader2 className="size-4 animate-spin" /> : <Upload className="size-4" />}
                {uploading ? 'Uploading…' : files.length > 0 ? 'Replace file(s)' : 'Click to upload'}
                {maxSize ? <span className="text-xs">(max {maxSize} KB)</span> : null}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept={accept}
                multiple={multiple}
                disabled={disabled}
                className="hidden"
                onChange={(event) => {
                    const picked = Array.from(event.target.files ?? []);

                    for (const file of picked) {
                        void uploadFile(file);
                    }
                }}
            />

            {files.length > 0 ? (
                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {files.map((file, index) => (
                        <li key={file.path} className="group relative overflow-hidden rounded-lg border border-border">
                            {preview && isImage(file) ? (
                                <img src={file.url} alt={file.name} className="h-20 w-full object-cover" />
                            ) : (
                                <div className="flex h-20 items-center justify-center px-2 text-center text-xs text-muted-foreground">
                                    <span className="truncate">{file.name}</span>
                                </div>
                            )}
                            <button
                                type="button"
                                onClick={() => removeFile(index)}
                                disabled={disabled}
                                aria-label={`Remove ${file.name}`}
                                className="absolute right-1 top-1 rounded-full bg-background/90 p-1 text-muted-foreground opacity-0 shadow transition group-hover:opacity-100 hover:text-destructive disabled:opacity-40"
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}

            {uploadError ? <p className="mt-1 text-sm text-destructive">{uploadError}</p> : null}
            {error ? <p className="mt-1 text-sm text-destructive">{error}</p> : null}
            {node.helperText ? <p className="mt-1 text-xs text-muted-foreground">{node.helperText}</p> : null}
        </div>
    );
}
