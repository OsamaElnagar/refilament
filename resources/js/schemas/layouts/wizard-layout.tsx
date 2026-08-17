import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { useState } from 'react';

import { Icon } from '@/components/icon';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { LayoutProps } from '@/schemas/registry';

/**
 * Wizard layout (mirrors Filament's multi-step form). Renders a numbered
 * step indicator; only the active step's schema shows. The active step is
 * pure client state - Back/Next never hit the server, and the whole form
 * submits together when the form is saved. A `skippable` wizard lets the
 * user jump forward without visiting every step.
 */
export default function WizardLayout({ node, renderChildren }: LayoutProps) {
    const steps = node.schema ?? [];
    const startOnStep = typeof node.startOnStep === 'number' ? node.startOnStep : 1;
    const [active, setActive] = useState<number>(Math.min(startOnStep, Math.max(steps.length, 1)));
    const skippable = node.skippable === true;

    if (steps.length === 0) {
        return null;
    }

    const isLast = active >= steps.length;

    const next = (): void => {
        if (isLast) {
            return;
        }

        setActive((value) => value + 1);
    };

    const previous = (): void => {
        setActive((value) => Math.max(value - 1, 1));
    };

    return (
        <div>
            {/* Step indicator: numbered circles + labels; the current step is
                highlighted, completed steps clickable when skippable. Each
                step may carry an icon and a short description. */}
            <ol className="mb-6 flex flex-wrap items-start gap-2">
                {steps.map((step, index) => {
                    const stepNumber = index + 1;
                    const isActive = stepNumber === active;
                    const isDone = stepNumber < active;
                    const canJump = skippable && isDone;

                    return (
                        <li key={step.label ?? index} className="flex items-center gap-2">
                            {index > 0 ? <span className="h-px w-6 bg-border" aria-hidden /> : null}
                            <button
                                type="button"
                                disabled={!canJump}
                                onClick={() => canJump && setActive(stepNumber)}
                                className={cn(
                                    'flex items-center gap-2 rounded-full py-1 pl-1 pr-3 text-sm font-medium transition',
                                    isActive && 'bg-primary/10 text-primary',
                                    !isActive && !canJump && 'cursor-default text-muted-foreground',
                                    canJump && 'hover:bg-muted',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        isActive
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                        isDone && 'bg-primary/20 text-primary',
                                    )}
                                >
                                    {isDone ? (
                                        <Check className="size-3.5" />
                                    ) : typeof step.icon === 'string' && step.icon ? (
                                        <Icon name={step.icon} size="sm" />
                                    ) : (
                                        stepNumber
                                    )}
                                </span>
                                <span className="flex flex-col items-start leading-tight">
                                    <span>{step.label}</span>
                                    {typeof step.description === 'string' && step.description ? (
                                        <span className="text-xs font-normal text-muted-foreground">
                                            {step.description}
                                        </span>
                                    ) : null}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>

            {/* Only the active step's fields render. */}
            {steps[active - 1] ? renderChildren(steps[active - 1].schema ?? []) : null}

            {/* Back / Next navigation. Next is hidden on the last step - the
                form's own submit footer closes the wizard. */}
            <div className="mt-6 flex items-center justify-between">
                <Button type="button" variant="outline" onClick={previous} disabled={active === 1}>
                    <ArrowLeft className="size-4" />
                    Back
                </Button>
                {!isLast ? (
                    <Button type="button" onClick={next}>
                        Next
                        <ArrowRight className="size-4" />
                    </Button>
                ) : null}
            </div>
        </div>
    );
}