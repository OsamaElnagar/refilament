import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';

interface ConfirmPasswordDialogProps {
    /** Whether the dialog is open. */
    open: boolean;
    /** Fired when the dialog requests to close (cancel, backdrop, escape). */
    onOpenChange: (open: boolean) => void;
    /**
     * Called once the password has been confirmed and the session's
     * `auth.password_confirmed_at` timestamp is fresh. Runs the actual
     * guarded action (enable 2FA, disable, regenerate). Return a rejected
     * promise if the guarded action itself returns 423 (session expired
     * between the confirm-password call and the action call), and the
     * dialog will stay open for the user to retry.
     */
    onConfirmed: () => Promise<void>;
    /** Dialog title — default: 'Confirm password'. */
    title?: string;
    /** Dialog description — default: explains why confirmation is needed. */
    description?: string;
}

/**
 * A modal that asks the user for their current password, POSTs it to
 * Fortify's `/user/confirm-password` endpoint (JSON mode), and on success
 * calls the `onConfirmed` callback. Handles the 423/422 lifecycle so the
 * caller only deals with success vs. failure semantics.
 *
 * The component is a controlled dialog — the parent manages its visibility
 * via `open` / `onOpenChange`. Opening it clears the previous password and
 * error state.
 */
export default function ConfirmPasswordDialog({
    open,
    onOpenChange,
    onConfirmed,
    title = 'Confirm password',
    description = 'For your security, please confirm your current password to continue.',
}: ConfirmPasswordDialogProps) {
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const handleSubmit = async () => {
        if (!password) {
            setError('Password is required.');
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            // 1. Confirm the password (sets auth.password_confirmed_at).
            const confirmRes = await fetch(panelUrl('/user/confirm-password'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken() ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ password }),
            });

            if (!confirmRes.ok) {
                if (confirmRes.status === 422) {
                    const body = await confirmRes.json().catch(() => null);
                    setError(body?.errors?.password?.[0] ?? 'The provided password was incorrect.');
                } else if (confirmRes.status === 423) {
                    setError('Session expired. Please try again.');
                } else {
                    setError('Unable to confirm password. Please try again.');
                }
                setProcessing(false);
                return;
            }

            // 2. Ready — session confirmed. Run the guarded action.
            await onConfirmed();

            // 3. Close the dialog only after a successful round.
            onOpenChange(false);
            setPassword('');
        } catch {
            setError('An unexpected error occurred. Please try again.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        handleSubmit();
                    }}
                    className="flex flex-col gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="confirm-password-password">Password</Label>
                        <Input
                            id="confirm-password-password"
                            type="password"
                            autoFocus
                            required
                            autoComplete="current-password"
                            value={password}
                            onChange={(e) => {
                                setPassword(e.target.value);
                                if (error) setError(null);
                            }}
                            disabled={processing}
                        />
                        {error ? (
                            <p className="text-sm text-destructive">{error}</p>
                        ) : null}
                    </div>

                    <DialogFooter className="sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Confirming…' : 'Confirm'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}