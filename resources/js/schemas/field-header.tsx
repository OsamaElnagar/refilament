import { router } from '@inertiajs/react';

import { iconFor } from '@/components/shell/PanelSidebar';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { FieldNode, HintAction } from '@/schemas/types';

/**
 * Field label row (slice C5) — mirrors Filament's label-row hint slot
 * (hint text + hint icon + hint actions, right of the label) for the fields
 * whose header is a simple row: text input, textarea, select. The label is a
 * real `<label htmlFor>` when `labelId` is given (the control's id), plain
 * text otherwise.
 *
 * Hint actions serialize as data: `visibleWhenFilled` is a pure client-side
 * rule evaluated against live form values (the honest counterpart to
 * Filament's runtime `visible(fn (Get $get) => ...)`), and clicking follows
 * `url` — a router visit, or a new tab when `openUrlInNewTab` is set. Server
 * closures never reach this component.
 */

function isFilled(value: unknown): boolean {
    return (
        value !== undefined &&
        value !== null &&
        value !== '' &&
        !(Array.isArray(value) && value.length === 0)
    );
}

function isHintActionVisible(action: HintAction, formValues?: Record<string, unknown>): boolean {
    if (!action.visibleWhenFilled || action.visibleWhenFilled.length === 0) {
        return true;
    }

    return action.visibleWhenFilled.every((field) => isFilled(formValues?.[field]));
}

function runHintAction(action: HintAction): void {
    if (!action.url) {
        return;
    }

    if (action.openUrlInNewTab) {
        window.open(action.url, '_blank', 'noopener,noreferrer');

        return;
    }

    router.visit(action.url);
}

function HintIconButton({ icon, tooltip }: { icon: string; tooltip?: string }): React.JSX.Element {
    const Icon = iconFor(icon);
    const hintIcon = (
        <span className="inline-flex">
            <Icon className="size-3.5" aria-hidden="true" />
        </span>
    );

    if (!tooltip) {
        return hintIcon;
    }

    return (
        <Tooltip>
            <TooltipTrigger render={hintIcon} />
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}

function HintActionButton({ action, formValues }: { action: HintAction; formValues?: Record<string, unknown> }): React.JSX.Element | null {
    if (!isHintActionVisible(action, formValues)) {
        return null;
    }

    const Icon = action.icon ? iconFor(action.icon) : null;

    const button = (
        <button
            type="button"
            onClick={() => runHintAction(action)}
            className="inline-flex items-center gap-1 rounded text-xs text-muted-foreground transition hover:text-foreground"
        >
            {Icon ? <Icon className="size-3.5" aria-hidden="true" /> : null}
            {action.label ? <span>{action.label}</span> : null}
        </button>
    );

    if (!action.tooltip) {
        return button;
    }

    return (
        <Tooltip>
            <TooltipTrigger render={button} />
            <TooltipContent>{action.tooltip}</TooltipContent>
        </Tooltip>
    );
}

interface FieldHeaderProps {
    node: FieldNode;
    formValues?: Record<string, unknown>;
    /** The control's id — renders the label as a real `<label htmlFor>`. */
    labelId?: string;
}

export default function FieldHeader({ node, formValues, labelId }: FieldHeaderProps): React.JSX.Element {
    const label = (
        <>
            {node.label}
            {node.required ? <span className="text-destructive"> *</span> : null}
        </>
    );

    const actions = node.hintActions ?? [];

    const hasRightSide =
        node.hint !== undefined ||
        node.hintIcon !== undefined ||
        actions.length > 0 ||
        node.helperText !== undefined;

    return (
        <div className="mb-1.5 flex items-baseline justify-between gap-2">
            {labelId ? (
                <Label htmlFor={labelId}>{label}</Label>
            ) : (
                <span className="text-sm font-medium">{label}</span>
            )}

            {hasRightSide ? (
                <div className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                    {node.hintIcon ? (
                        <HintIconButton icon={node.hintIcon.icon} tooltip={node.hintIcon.tooltip} />
                    ) : null}
                    {node.hint ? <span>{node.hint}</span> : null}
                    {actions.map((action) => (
                        <HintActionButton key={action.name} action={action} formValues={formValues} />
                    ))}
                    {node.helperText ? <span>{node.helperText}</span> : null}
                </div>
            ) : null}
        </div>
    );
}
