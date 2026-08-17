import { Bold, Eraser, Italic, Link, List, ListOrdered, Underline } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { cn } from '@/lib/utils';
import type { FieldProps } from '@/schemas/registry';

const ALL_BUTTONS = ['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'clear'];

/**
 * Rich editor field (mirrors Filament's RichEditor) — a contenteditable HTML
 * editor whose value is an HTML string. A lightweight toolbar drives
 * document.execCommand (the same commands TipTap wraps); the serialized value
 * is raw HTML, sanitized server-side before any rendering. `toolbarButtons`
 * restricts the visible buttons; `maxHeight` caps the editor box.
 */
export default function RichEditorField({ node, value, error, onChange, disabled }: FieldProps) {
    const editorRef = useRef<HTMLDivElement>(null);
    const lastValueRef = useRef<string | undefined>(undefined);
    const buttons = Array.isArray(node.toolbarButtons) ? node.toolbarButtons : ALL_BUTTONS;
    const maxHeight = typeof node.maxHeight === 'number' ? node.maxHeight : undefined;

    // Set the editor's HTML only when the incoming value differs from what
    // the DOM already holds — re-applying our own echoed value on every
    // keystroke would clobber the caret. External resets (a reload after
    // save) still land because the DOM differs then.
    useEffect(() => {
        const el = editorRef.current;

        if (!el) {
            return;
        }

        const html = typeof value === 'string' ? value : '';

        if (lastValueRef.current !== value && el.innerHTML !== html) {
            el.innerHTML = html;
        }

        lastValueRef.current = html;
    }, [value]);

    const run = (command: string, arg?: string): void => {
        editorRef.current?.focus();
        document.execCommand(command, false, arg);
        onChange?.(editorRef.current?.innerHTML ?? '');
    };

    const isEnabled = (button: string): boolean => buttons.includes(button);

    return (
        <div className="space-y-2">
            <label className="text-sm font-medium leading-none text-foreground">
                {node.label ?? node.name}
                {node.required ? <span className="ml-0.5 text-destructive">*</span> : null}
            </label>

            <div
                className={cn(
                    'overflow-hidden rounded-lg border border-border focus-within:ring-2 focus-within:ring-ring',
                    error && 'border-destructive',
                    disabled && 'opacity-50',
                )}
            >
                {isEnabled('bold') || isEnabled('italic') || isEnabled('underline') || isEnabled('bulletList') || isEnabled('orderedList') || isEnabled('link') || isEnabled('clear') ? (
                    <div className="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/40 px-2 py-1">
                        {isEnabled('bold') ? (
                            <ToolbarButton label="Bold" onClick={() => run('bold')}>
                                <Bold className="size-3.5" />
                            </ToolbarButton>
                        ) : null}
                        {isEnabled('italic') ? (
                            <ToolbarButton label="Italic" onClick={() => run('italic')}>
                                <Italic className="size-3.5" />
                            </ToolbarButton>
                        ) : null}
                        {isEnabled('underline') ? (
                            <ToolbarButton label="Underline" onClick={() => run('underline')}>
                                <Underline className="size-3.5" />
                            </ToolbarButton>
                        ) : null}
                        {isEnabled('bulletList') ? (
                            <ToolbarButton label="Bullet list" onClick={() => run('insertUnorderedList')}>
                                <List className="size-3.5" />
                            </ToolbarButton>
                        ) : null}
                        {isEnabled('orderedList') ? (
                            <ToolbarButton label="Numbered list" onClick={() => run('insertOrderedList')}>
                                <ListOrdered className="size-3.5" />
                            </ToolbarButton>
                        ) : null}
                        {isEnabled('link') ? (
                            <ToolbarButton
                                label="Insert link"
                                onClick={() => {
                                    const url = window.prompt('Link URL');

                                    if (url) {
                                        run('createLink', url);
                                    }
                                }}
                            >
                                <Link className="size-3.5" />
                            </ToolbarButton>
                        ) : null}
                        {isEnabled('clear') ? (
                            <ToolbarButton label="Clear formatting" onClick={() => run('removeFormat')}>
                                <Eraser className="size-3.5" />
                            </ToolbarButton>
                        ) : null}
                    </div>
                ) : null}

                <div
                    ref={editorRef}
                    contentEditable={!disabled}
                    suppressContentEditableWarning
                    data-placeholder={node.placeholder}
                    className={cn(
                        'min-h-28 px-3 py-2 text-sm text-foreground outline-none [&:empty:before]:text-muted-foreground [&:empty:before]:content-[attr(data-placeholder)]',
                        maxHeight && 'overflow-y-auto',
                    )}
                    style={maxHeight ? { maxHeight } : undefined}
                    onInput={(event) => {
                        const html = (event.currentTarget as HTMLDivElement).innerHTML;
                        lastValueRef.current = html;
                        onChange?.(html);
                    }}
                />
            </div>

            {error ? <p className="mt-1 text-sm text-destructive">{error}</p> : null}
            {node.helperText ? <p className="mt-1 text-xs text-muted-foreground">{node.helperText}</p> : null}
        </div>
    );
}

function ToolbarButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
            className="rounded p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
        >
            {children}
        </button>
    );
}
