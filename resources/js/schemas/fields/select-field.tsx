import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Check, ChevronDown } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import FieldHeader from '@/schemas/field-header';
import type { FieldProps } from '@/schemas/registry';

export default function SelectField({
    node,
    value,
    error,
    options: resolvedOptions,
    loading,
    onChange,
    formValues,
}: FieldProps) {
    const options = resolvedOptions ?? node.options ?? [];
    const multiple = node.multiple ?? false;
    const searchable = node.searchable ?? false;
    const disabled = node.disabled ?? node.readOnly ?? false;
    const isDependent = (node.dependsOn?.length ?? 0) > 0;

    const selectedValues = useMemo<string[]>(() => {
        const raw = value ?? node.default;

        if (multiple) {
            return Array.isArray(raw) ? raw.map(String) : raw != null ? [String(raw)] : [];
        }

        return raw != null ? [String(raw)] : [];
    }, [value, node.default, multiple]);

    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlighted, setHighlighted] = useState(0);
    const rootRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    const visibleOptions = useMemo(() => {
        const q = query.trim().toLowerCase();

        return q ? options.filter((option) => option.label.toLowerCase().includes(q)) : options;
    }, [options, query]);

    const safeHighlighted = Math.min(highlighted, Math.max(visibleOptions.length - 1, 0));

    const isSelected = (optionValue: string): boolean => selectedValues.includes(optionValue);

    const selectValue = (optionValue: string): void => {
        if (multiple) {
            const next = selectedValues.includes(optionValue)
                ? selectedValues.filter((value) => value !== optionValue)
                : [...selectedValues, optionValue];

            onChange?.(next);

            return;
        }

        onChange?.(optionValue);
        setIsOpen(false);
    };

    const open = (): void => {
        if (disabled) {
            return;
        }

        setQuery('');
        setHighlighted(0);
        setIsOpen(true);
    };

    const toggle = (): void => {
        if (disabled) {
            return;
        }

        if (isOpen) {
            setIsOpen(false);

            return;
        }

        open();
    };

    const moveHighlight = (delta: number): void => {
        if (visibleOptions.length === 0) {
            return;
        }

        setHighlighted((current) => {
            const clamped = Math.min(current, visibleOptions.length - 1);

            return Math.min(Math.max(clamped + delta, 0), visibleOptions.length - 1);
        });
    };

    useEffect(() => {
        if (isOpen && searchable) {
            window.setTimeout(() => searchRef.current?.focus(), 0);
        }
    }, [isOpen, searchable]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const onPointerDown = (event: MouseEvent): void => {
            if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [isOpen]);

    const onTriggerKeyDown = (event: KeyboardEvent<HTMLButtonElement>): void => {
        if (disabled) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggle();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (!isOpen) {
                open();

                return;
            }

            moveHighlight(event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Escape') {
            setIsOpen(false);
        }
    };

    // Handles keys bubbling up from the search input and the list, so keyboard
    // selection works in both searchable and plain modes.
    const onPanelKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key === 'Enter') {
            event.preventDefault();
            const option = visibleOptions[safeHighlighted];

            if (option) {
                selectValue(option.value);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setIsOpen(false);
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            moveHighlight(event.key === 'ArrowDown' ? 1 : -1);
        }
    };

    const selectedLabels = useMemo(
        () => selectedValues.map((value) => options.find((option) => option.value === value)?.label ?? value),
        [selectedValues, options],
    );

    const triggerLabel =
        selectedLabels.length > 0
            ? selectedLabels.join(', ')
            : (node.placeholder ?? (multiple ? 'Select options…' : 'Select an option'));

    return (
        <div ref={rootRef}>
            <FieldHeader node={node} formValues={formValues} />

            <div className="relative">
                <Button
                    type="button"
                    variant="outline"
                    onClick={toggle}
                    onKeyDown={onTriggerKeyDown}
                    aria-haspopup="listbox"
                    aria-expanded={isOpen}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between font-normal',
                        selectedLabels.length === 0 && 'text-muted-foreground',
                        error && 'border-destructive focus-visible:ring-destructive/30',
                    )}
                >
                    <span className="truncate">{triggerLabel}</span>
                    <ChevronDown className="size-4 shrink-0 opacity-50" />
                </Button>

                {isOpen ? (
                    <div
                        onKeyDown={onPanelKeyDown}
                        className="absolute z-10 mt-1 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
                    >
                        {searchable ? (
                            <div className="border-b p-2">
                                <Input
                                    ref={searchRef}
                                    type="text"
                                    value={query}
                                    onChange={(event) => {
                                        setQuery(event.target.value);
                                        setHighlighted(0);
                                    }}
                                    placeholder="Search…"
                                    className="h-8 text-sm"
                                />
                            </div>
                        ) : null}

                        <ul
                            role="listbox"
                            aria-multiselectable={multiple || undefined}
                            className="max-h-56 overflow-auto py-1"
                        >
                            {isDependent && loading && options.length === 0 ? (
                                <li className="px-3 py-2 text-sm text-muted-foreground">Loading options…</li>
                            ) : visibleOptions.length === 0 ? (
                                <li className="px-3 py-2 text-sm text-muted-foreground">No results</li>
                            ) : (
                                visibleOptions.map((option, index) => {
                                    const active = isSelected(option.value);

                                    return (
                                        <li
                                            key={option.value}
                                            role="option"
                                            aria-selected={active}
                                            onMouseEnter={() => setHighlighted(index)}
                                            onClick={() => selectValue(option.value)}
                                            className={cn(
                                                'flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm transition',
                                                index === safeHighlighted && 'bg-accent',
                                                active ? 'font-medium text-foreground' : 'text-muted-foreground',
                                            )}
                                        >
                                            <span className="truncate">{option.label}</span>
                                            {active ? (
                                                <span className="text-primary">
                                                    <Check className="size-4" />
                                                </span>
                                            ) : null}
                                        </li>
                                    );
                                })
                            )}
                        </ul>
                    </div>
                ) : null}
            </div>

            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
