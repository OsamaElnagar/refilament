import { useRef, useState } from 'react';
import { Check, Copy, Eye, EyeOff, Loader2 } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { FieldProps } from '@/schemas/registry';

const COPY_FEEDBACK_MS = 2000;

export default function TextInputField({ node, value, error, checking, onChange }: FieldProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isRevealed, setIsRevealed] = useState(false);
    const [isCopied, setIsCopied] = useState(false);

    const inputType = node.inputType ?? 'text';
    const isPassword = inputType === 'password';
    const showReveal = isPassword && (node.revealable ?? false);
    const showCopy = node.copyable ?? false;
    const hasSuffixAction = showReveal || showCopy || (checking ?? false);

    const handleCopy = async () => {
        const current = inputRef.current?.value ?? '';

        if (!current) {
            return;
        }

        try {
            await navigator.clipboard.writeText(current);
            setIsCopied(true);
            window.setTimeout(() => setIsCopied(false), COPY_FEEDBACK_MS);
        } catch {
            // Clipboard unavailable — silently ignore for the demo.
        }
    };

    return (
        <div>
            <div className="mb-1.5 flex items-baseline justify-between gap-2">
                <Label htmlFor={node.name}>
                    {node.label}
                    {node.required ? <span className="text-destructive"> *</span> : null}
                </Label>

                {node.helperText ? (
                    <span className="text-xs text-muted-foreground">{node.helperText}</span>
                ) : null}
            </div>

            <div className="relative">
                <Input
                    ref={inputRef}
                    id={node.name}
                    type={isPassword && showReveal ? (isRevealed ? 'text' : 'password') : inputType}
                    name={node.name}
                    value={(value as string | undefined) ?? (node.default as string | undefined) ?? ''}
                    onChange={(event) => onChange?.(event.target.value)}
                    placeholder={node.placeholder}
                    maxLength={node.maxLength}
                    min={node.minValue}
                    max={node.maxValue}
                    step={node.step}
                    disabled={node.disabled ?? false}
                    autoFocus={node.autofocus ?? false}
                    aria-invalid={error ? true : undefined}
                    className={cn(hasSuffixAction && 'pr-16', error && 'border-destructive focus-visible:ring-destructive/30')}
                />

                {hasSuffixAction ? (
                    <div className="absolute inset-y-0 right-0 flex items-center gap-0.5 pr-2">
                        {checking ? (
                            <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden="true" />
                        ) : null}

                        {showCopy ? (
                            <button
                                type="button"
                                onClick={handleCopy}
                                title={isCopied ? (node.copyMessage ?? 'Copied!') : 'Copy'}
                                aria-label={isCopied ? (node.copyMessage ?? 'Copied!') : 'Copy'}
                                className="rounded-md p-1.5 text-muted-foreground transition hover:bg-accent hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {isCopied ? (
                                    <Check className="size-4" aria-hidden="true" />
                                ) : (
                                    <Copy className="size-4" aria-hidden="true" />
                                )}
                            </button>
                        ) : null}

                        {showReveal ? (
                            <button
                                type="button"
                                onClick={() => setIsRevealed((current) => !current)}
                                title={isRevealed ? 'Hide password' : 'Show password'}
                                aria-label={isRevealed ? 'Hide password' : 'Show password'}
                                className="rounded-md p-1.5 text-muted-foreground transition hover:bg-accent hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {isRevealed ? (
                                    <EyeOff className="size-4" aria-hidden="true" />
                                ) : (
                                    <Eye className="size-4" aria-hidden="true" />
                                )}
                            </button>
                        ) : null}
                    </div>
                ) : null}
            </div>

            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
