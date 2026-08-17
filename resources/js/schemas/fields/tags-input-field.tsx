import { useEffect, useMemo, useRef, useState } from 'react';
import { Plus, X } from 'lucide-react';

import { cn } from '@/lib/utils';
import FieldHeader from '@/schemas/field-header';
import type { FieldProps } from '@/schemas/registry';

function toTags(value: unknown, separator: string | null): string[] {
    if (Array.isArray(value)) {
        return value.map(String).filter((tag) => tag !== '');
    }

    if (typeof value === 'string' && value !== '' && separator) {
        return value
            .split(separator)
            .map((tag) => tag.trim())
            .filter((tag) => tag !== '');
    }

    return [];
}

export default function TagsInputField({ node, value, error, onChange, formValues }: FieldProps) {
    const separator = node.separator ?? ',';
    const splitKeys = node.splitKeys ?? ['Enter'];
    const suggestions = node.suggestions ?? [];
    const tagPrefix = node.tagPrefix ?? '';
    const tagSuffix = node.tagSuffix ?? '';
    const reorderable = node.reorderable ?? false;
    const disabled = node.disabled ?? node.readOnly ?? false;

    const [tags, setTags] = useState<string[]>(() => toTags(value, separator));
    const [draft, setDraft] = useState('');
    const [draggedIndex, setDraggedIndex] = useState<number | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // Keep local state in sync when the form resets/rehydrates the value.
    const serialized = useMemo(() => JSON.stringify(toTags(value, separator)), [value, separator]);

    useEffect(() => {
        setTags(toTags(value, separator));
    }, [serialized]);

    const commitTags = (nextTags: string[]) => {
        setTags(nextTags);
        onChange?.(nextTags);
    };

    const addTag = (raw: string) => {
        const tag = raw.trim();

        if (!tag || tags.includes(tag)) {
            return;
        }

        commitTags([...tags, tag]);
        setDraft('');
    };

    const removeTag = (index: number) => {
        commitTags(tags.filter((_, tagIndex) => tagIndex !== index));
    };

    const reorder = (from: number, to: number) => {
        if (from === to) {
            return;
        }

        const nextTags = [...tags];
        const [moved] = nextTags.splice(from, 1);
        nextTags.splice(to, 0, moved);
        commitTags(nextTags);
    };

    const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (disabled) {
            return;
        }

        if (event.key === 'Backspace' && draft === '' && tags.length > 0) {
            removeTag(tags.length - 1);

            return;
        }

        if (splitKeys.includes(event.key)) {
            event.preventDefault();
            addTag(draft);

            return;
        }

        if (draft.includes(separator)) {
            event.preventDefault();
            addTag(draft.split(separator)[0]);
        }
    };

    const visibleSuggestions = suggestions.filter(
        (suggestion) => !tags.includes(suggestion) && (!draft || suggestion.toLowerCase().includes(draft.toLowerCase())),
    );

    return (
        <div>
            <FieldHeader node={node} formValues={formValues} labelId={node.name} />

            <div
                className={cn(
                    'flex flex-wrap items-center gap-1.5 rounded-md border bg-background px-3 py-2 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-0',
                    error && 'border-destructive focus-within:ring-destructive/30',
                    disabled && 'opacity-50',
                )}
            >
                {tags.map((tag, index) => (
                    <span
                        key={`${tag}-${index}`}
                        draggable={reorderable && !disabled}
                        onDragStart={() => setDraggedIndex(index)}
                        onDragOver={(event) => {
                            if (reorderable) {
                                event.preventDefault();
                            }
                        }}
                        onDrop={() => {
                            if (reorderable && draggedIndex !== null) {
                                reorder(draggedIndex, index);
                                setDraggedIndex(null);
                            }
                        }}
                        className={cn(
                            'inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground',
                            reorderable && 'cursor-grab',
                        )}
                    >
                        <span className="inline-flex items-center">
                            {tagPrefix ? <span className="opacity-60">{tagPrefix}</span> : null}
                            {tag}
                            {tagSuffix ? <span className="opacity-60">{tagSuffix}</span> : null}
                        </span>

                        {!disabled ? (
                            <button
                                type="button"
                                onClick={() => removeTag(index)}
                                aria-label={`Remove ${tag}`}
                                className="rounded-sm p-0.5 text-secondary-foreground/60 transition hover:bg-accent hover:text-foreground"
                            >
                                <X className="size-3" />
                            </button>
                        ) : null}
                    </span>
                ))}

                <input
                    ref={inputRef}
                    id={node.name}
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={handleKeyDown}
                    onBlur={() => {
                        if (draft.trim()) {
                            addTag(draft);
                        }
                    }}
                    placeholder={tags.length === 0 ? (node.placeholder ?? 'Add a tag…') : ''}
                    disabled={disabled}
                    autoFocus={node.autofocus ?? false}
                    aria-invalid={error ? true : undefined}
                    className="min-w-24 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                />
            </div>

            {visibleSuggestions.length > 0 ? (
                <div className="mt-1.5 flex flex-wrap gap-1.5">
                    {visibleSuggestions.map((suggestion) => (
                        <button
                            key={suggestion}
                            type="button"
                            disabled={disabled}
                            onClick={() => addTag(suggestion)}
                            className="inline-flex items-center gap-1 rounded-full border border-dashed px-2 py-0.5 text-xs text-muted-foreground transition hover:border-solid hover:bg-accent hover:text-foreground"
                        >
                            <Plus className="size-3" />
                            {suggestion}
                        </button>
                    ))}
                </div>
            ) : null}

            {error ? <p className="mt-0.5 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}