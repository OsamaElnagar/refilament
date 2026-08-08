import { Plus } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import ActionModal from '@/tables/action-modal';
import type { TableAction } from '@/tables/types';

interface HeaderActionsProps {
    actions: TableAction[];
    /** Called after a modal action succeeds so the table refetches. */
    onSucceeded: () => void;
    /**
     * Optional per-action submit endpoint override (slice 1.8 — a relation
     * manager's create submits to the manager's action route rather than the
     * schema submit endpoint, so the record is attached to the owner).
     */
    submitUrlFor?: (actionName: string) => string;
}

/**
 * Renders the table's header actions (slice 1.1, docs/CONTRACT.md, "Modal
 * actions"). A `create`-type action opens the shared ActionModal hosting the
 * linked form schema document, submits it through the typed submit endpoint,
 * and on success closes the dialog and refetches the table.
 */
export default function HeaderActions({ actions, onSucceeded, submitUrlFor }: HeaderActionsProps) {
    const [open, setOpen] = useState<TableAction | null>(null);

    const modalActions = actions.filter((action) => action.type === 'create' && action.schema !== undefined);

    return (
        <>
            {modalActions.map((action) => (
                <Button key={action.name} type="button" className="gap-1.5" onClick={() => setOpen(action)}>
                    <Plus className="size-4" aria-hidden="true" />
                    {action.label}
                </Button>
            ))}

            {open !== null ? (
                <ActionModal
                    action={open}
                    open
                    onClose={() => setOpen(null)}
                    onSucceeded={onSucceeded}
                    submitUrl={submitUrlFor?.(open.name)}
                />
            ) : null}
        </>
    );
}
