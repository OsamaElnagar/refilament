import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

import { iconFor } from '@/components/shell/PanelSidebar';
import { cn } from '@/lib/utils';
import { readCsrfToken } from '@/lib/csrf';
import { renderNotification } from '@/notifications/renderNotification';
import type { NotificationPayload } from '@/notifications/renderNotification';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import ActionModal from '@/tables/action-modal';
import type { TableActionColor, TableActionType } from '@/tables/types';

/**
 * One serialized page header action (slice 1.10 — docs/CONTRACT.md, "Page
 * actions"). The server filters unauthorized actions, resolves a
 * CreateAction's url/modal fallback, and — on record pages — resolves
 * EditAction/ViewAction per-record URLs and marks server actions (DeleteAction)
 * with their runnable endpoint. The client only renders what it received.
 */
export interface PageAction {
    name: string;
    label: string;
    /** Omitted unless configured. */
    color?: TableActionColor;
    /**
     * The modal behavior: 'create' opens the linked form in a dialog (the
     * fallback for a default CreateAction when the resource has no create
     * page).
     */
    type?: TableActionType;
    /** The form schema document id a modal action hosts. */
    schema?: string;
    /** Navigate to this URL on click — a router visit, or a new tab when
     * openUrlInNewTab is set. */
    url?: string;
    openUrlInNewTab?: boolean;
    /** Omitted unless configured. */
    icon?: string;
    /** Omitted unless configured. */
    tooltip?: string;
    /** Show a confirmation dialog before running. */
    requiresConfirmation?: boolean;
    /**
     * A record-scoped server action (DeleteAction on an edit/view page): the
     * endpoint that runs the action's closure against the page's record.
     * Present exactly when the action runs on the server instead of
     * navigating.
     */
    actionUrl?: string;
    /**
     * Where to navigate after a server action succeeds (DeleteAction lands on
     * the resource's list page — the record is gone, so reloading in place
     * would 404). Absent means the caller's onSucceeded() reload is used.
     */
    redirect?: string;
}

interface PageActionsProps {
    actions: PageAction[];
    /** Called after a modal action succeeds so the page refreshes. */
    onSucceeded: () => void;
}

/**
 * JSON POST headers carrying the session's CSRF token — the panel routes run
 * inside the framework's `web` middleware group, so every POST must validate.
 */
function postHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    };

    const csrfToken = readCsrfToken();

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    return headers;
}

function runUrlAction(action: PageAction): void {
    if (!action.url) {
        return;
    }

    if (action.openUrlInNewTab) {
        window.open(action.url, '_blank', 'noopener,noreferrer');

        return;
    }

    router.visit(action.url);
}

async function runServerAction(action: PageAction, onSucceeded: () => void): Promise<void> {
    if (!action.actionUrl) {
        return;
    }

    try {
        const response = await fetch(action.actionUrl, {
            method: 'POST',
            headers: postHeaders(),
        });

        const payload = (await response.json()) as {
            success?: boolean;
            message?: string;
            notification?: NotificationPayload;
            errors?: Record<string, string[]>;
        };

        if (!response.ok) {
            throw new Error(payload.errors?.action?.[0] ?? `Action returned ${response.status}`);
        }

        if (payload.notification) {
            renderNotification(payload.notification);
        } else {
            toast.success(payload.message ?? `${action.label} succeeded.`);
        }

        if (action.redirect) {
            router.visit(action.redirect);
        } else {
            onSucceeded();
        }
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'Action failed.');
    }
}

function PageActionButton({
    action,
    onOpenModal,
    onConfirm,
}: {
    action: PageAction;
    onOpenModal: () => void;
    onConfirm: () => void;
}): React.JSX.Element {
    const Icon = action.icon ? iconFor(action.icon) : null;
    const isModal = action.type === 'create' && action.schema !== undefined;
    const runsOnServer = action.actionUrl !== undefined;
    const enabled = Boolean(action.url) || isModal || runsOnServer;

    const button = (
        <Button
            type="button"
            className="gap-1.5"
            disabled={!enabled}
            onClick={() => {
                if (isModal) {
                    onOpenModal();

                    return;
                }

                if (runsOnServer) {
                    onConfirm();

                    return;
                }

                runUrlAction(action);
            }}
        >
            {Icon ? <Icon className="size-4" aria-hidden="true" /> : null}
            {action.label}
        </Button>
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

/**
 * Renders a page's serialized header actions (slice 1.10) in the page
 * header, right of the title — the React analogue of Filament's page-header
 * actions. A url action navigates (or opens a new tab); a modal create opens
 * the shared ActionModal hosting the linked form schema, refreshing the page
 * on success; a record-scoped server action (DeleteAction) confirms when
 * required, POSTs to its serialized endpoint, toasts, then follows the
 * server-serialized redirect (the list page after a delete) or reloads.
 * A plain action (no url, no modal schema, no endpoint) renders disabled —
 * the client never guesses a behavior the server didn't serialize.
 */
export default function PageActions({ actions, onSucceeded }: PageActionsProps): React.JSX.Element | null {
    const [modalAction, setModalAction] = useState<PageAction | null>(null);
    const [confirmAction, setConfirmAction] = useState<PageAction | null>(null);

    if (actions.length === 0) {
        return null;
    }

    return (
        <>
            <div className="flex shrink-0 items-center gap-2">
                {actions.map((action) => (
                    <PageActionButton
                        key={action.name}
                        action={action}
                        onOpenModal={() => setModalAction(action)}
                        onConfirm={() => {
                            if (action.requiresConfirmation) {
                                setConfirmAction(action);
                            } else {
                                void runServerAction(action, onSucceeded);
                            }
                        }}
                    />
                ))}
            </div>

            {modalAction !== null ? (
                <ActionModal
                    action={modalAction}
                    open
                    onClose={() => setModalAction(null)}
                    onSucceeded={onSucceeded}
                />
            ) : null}

            <AlertDialog
                open={confirmAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmAction(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {confirmAction?.label ? `Confirm ${confirmAction.label}` : 'Confirm action'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This runs the “{confirmAction?.label ?? 'action'}” action. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className={cn(
                                confirmAction?.color === 'danger' &&
                                    'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                            )}
                            onClick={() => {
                                if (confirmAction) {
                                    void runServerAction(confirmAction, onSucceeded);
                                }
                            }}
                        >
                            {confirmAction?.label ?? 'Confirm'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
