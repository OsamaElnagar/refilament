import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { CheckIcon, CopyIcon, ShieldCheckIcon, ShieldOffIcon, TriangleAlertIcon } from 'lucide-react';
import { toast } from 'sonner';

import ConfirmPasswordDialog from '@/components/auth/ConfirmPasswordDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';

interface TwoFactorSectionProps {
    /** A confirmed two-factor secret exists on the authenticated user. */
    enabled: boolean;
    /** A two-factor secret exists but has not yet been confirmed (mid-setup). */
    enabling: boolean;
}

/**
 * The two-factor authentication management cards — enable/disable, QR code,
 * secret key, recovery codes. Used by both the standalone `TwoFactorSettings`
 * page and the `EditProfile` page when the panel has 2FA enabled.
 *
 * Renders without a shell wrapper or header, so it can be embedded anywhere
 * inside the panel shell.
 */
export default function TwoFactorSection({ enabled, enabling }: TwoFactorSectionProps) {
    const [displayState] = useState<'disabled' | 'enabling' | 'enabled'>(
        enabled ? 'enabled' : enabling ? 'enabling' : 'disabled',
    );

    // QR code & secret key (fetched when enabling).
    const [qrSvg, setQrSvg] = useState<string | null>(null);
    const [secretKey, setSecretKey] = useState<string | null>(null);
    const [qrLoading, setQrLoading] = useState(false);
    const [qrError, setQrError] = useState<string | null>(null);

    // Code confirmation.
    const [code, setCode] = useState('');
    const [codeError, setCodeError] = useState<string | null>(null);
    const [codeProcessing, setCodeProcessing] = useState(false);

    // Recovery codes.
    const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
    const [codesLoading, setCodesLoading] = useState(false);
    const [codesRevealed, setCodesRevealed] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const [copied, setCopied] = useState(false);

    // Password confirmation dialog.
    const [passwordDialogOpen, setPasswordDialogOpen] = useState(false);
    const pendingActionRef = useRef<(() => Promise<void>) | null>(null);

    // --- Helper: fetch QR + secret when enabling ---
    const loadQr = async () => {
        setQrLoading(true);
        setQrError(null);

        try {
            const [qrRes, secretRes] = await Promise.all([
                fetch(panelUrl('/user/two-factor-qr-code'), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }),
                fetch(panelUrl('/user/two-factor-secret-key'), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }),
            ]);

            if (qrRes.status === 423 || secretRes.status === 423) {
                setQrError('Session expired — please confirm your password again.');
                setQrLoading(false);
                return;
            }

            if (!qrRes.ok) {
                setQrError('Failed to load QR code. Please try again.');
                setQrLoading(false);
                return;
            }

            const qrData = await qrRes.json();
            setQrSvg(qrData.svg ?? null);

            if (secretRes.ok) {
                const secretData = await secretRes.json();
                setSecretKey(secretData.secretKey ?? null);
            }

            setQrLoading(false);
        } catch {
            setQrError('An unexpected error occurred. Please try again.');
            setQrLoading(false);
        }
    };

    useEffect(() => {
        if (displayState === 'enabling') {
            loadQr();
        }
    }, [displayState]);

    // --- Helper: fetch recovery codes ---
    const fetchRecoveryCodes = async () => {
        setCodesLoading(true);
        try {
            const res = await fetch(panelUrl('/user/two-factor-recovery-codes'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': readCsrfToken() ?? '',
                },
            });

            if (res.status === 423) {
                pendingActionRef.current = fetchRecoveryCodes;
                setPasswordDialogOpen(true);
                setCodesLoading(false);
                return;
            }

            if (!res.ok) {
                toast.error('Failed to load recovery codes.');
                setCodesLoading(false);
                return;
            }

            const codes: string[] = await res.json();
            setRecoveryCodes(codes);
            setCodesRevealed(true);
        } catch {
            toast.error('Failed to load recovery codes.');
        } finally {
            setCodesLoading(false);
        }
    };

    // --- Copy all ---
    const copyAllCodes = async () => {
        if (!recoveryCodes) return;
        try {
            await navigator.clipboard.writeText(recoveryCodes.join('\n'));
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
            toast.success('Recovery codes copied to clipboard.');
        } catch {
            toast.error('Failed to copy codes.');
        }
    };

    // --- Copy a single code ---
    const copySingleCode = async (code: string) => {
        try {
            await navigator.clipboard.writeText(code);
            toast.success('Code copied to clipboard.');
        } catch {
            // Ignore.
        }
    };

    // --- Guarded action wrapper ---
    const runGuarded = (action: () => Promise<void>) => {
        pendingActionRef.current = action;
        setPasswordDialogOpen(true);
    };

    // --- Enable 2FA ---
    const handleEnable = async () => {
        const res = await fetch(panelUrl('/user/two-factor-authentication'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken() ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (res.status === 423) throw new Error('SESSION_EXPIRED');
        if (!res.ok) { toast.error('Failed to enable two-factor authentication.'); return; }
        router.reload();
    };

    // --- Confirm the code ---
    const handleConfirmCode = async () => {
        if (!code) { setCodeError('Please enter the code from your authenticator app.'); return; }
        setCodeProcessing(true);
        setCodeError(null);

        try {
            const res = await fetch(panelUrl('/user/confirmed-two-factor-authentication'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken() ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ code }),
            });

            if (res.status === 423) {
                pendingActionRef.current = handleConfirmCode;
                setPasswordDialogOpen(true);
                setCodeProcessing(false);
                return;
            }

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                setCodeError(body?.errors?.code?.[0] ?? 'The code is invalid or expired. Please try again.');
                setCodeProcessing(false);
                return;
            }

            router.reload();
        } catch {
            setCodeError('An unexpected error occurred. Please try again.');
            setCodeProcessing(false);
        }
    };

    // --- Disable 2FA ---
    const handleDisable = async () => {
        const res = await fetch(panelUrl('/user/two-factor-authentication'), {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': readCsrfToken() ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (res.status === 423) throw new Error('SESSION_EXPIRED');
        if (!res.ok) { toast.error('Failed to disable two-factor authentication.'); return; }
        router.reload();
    };

    // --- Regenerate recovery codes ---
    const handleRegenerate = async () => {
        setRegenerating(true);
        try {
            const res = await fetch(panelUrl('/user/two-factor-recovery-codes'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken() ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (res.status === 423) {
                pendingActionRef.current = handleRegenerate;
                setPasswordDialogOpen(true);
                setRegenerating(false);
                return;
            }

            if (!res.ok) { toast.error('Failed to regenerate recovery codes.'); setRegenerating(false); return; }
            await fetchRecoveryCodes();
            toast.success('Recovery codes regenerated.');
        } catch {
            toast.error('Failed to regenerate recovery codes.');
        } finally { setRegenerating(false); }
    };

    // --- Password-confirmed callback ---
    const handlePasswordConfirmed = async () => {
        const action = pendingActionRef.current;
        pendingActionRef.current = null;
        if (!action) return;
        try {
            await action();
        } catch (err) {
            if (err instanceof Error && err.message === 'SESSION_EXPIRED') {
                pendingActionRef.current = action;
                setPasswordDialogOpen(true);
            } else {
                toast.error('Action failed. Please try again.');
            }
        }
    };

    return (
        <>
            {/* --- Disabled state --- */}
            {displayState === 'disabled' && (
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                                <ShieldOffIcon className="size-5 text-muted-foreground" />
                            </div>
                            <div>
                                <CardTitle>Two-factor authentication</CardTitle>
                                <CardDescription>Add an extra layer of security to your account.</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Two-factor authentication is currently <strong>disabled</strong>.
                            When enabled, you&apos;ll be prompted for a code from your
                            authenticator app in addition to your password.
                        </p>
                        <Button onClick={() => runGuarded(handleEnable)}>
                            <ShieldCheckIcon className="mr-2 size-4" />
                            Enable two-factor authentication
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* --- Enabling state --- */}
            {displayState === 'enabling' && (
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Step 1: Scan the QR code</CardTitle>
                            <CardDescription>
                                Use your authenticator app (e.g. Google Authenticator, Authy, or 1Password)
                                to scan the QR code below.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center gap-4">
                            {qrLoading && (
                                <div className="flex flex-col items-center gap-3">
                                    <Skeleton className="size-48 rounded-lg" />
                                    <Skeleton className="h-4 w-48" />
                                </div>
                            )}
                            {qrError && !qrLoading && (
                                <div className="flex flex-col items-center gap-3 text-center">
                                    <TriangleAlertIcon className="size-8 text-destructive" />
                                    <p className="text-sm text-destructive">{qrError}</p>
                                    <Button variant="outline" size="sm" onClick={() => loadQr()}>Retry</Button>
                                </div>
                            )}
                            {qrSvg && !qrLoading && !qrError && (
                                <>
                                    <div className="rounded-lg border bg-white p-4" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                                    {secretKey && (
                                        <div className="flex w-full max-w-sm items-center gap-2">
                                            <code className="flex-1 truncate rounded-md bg-muted px-3 py-1.5 text-xs font-mono">{secretKey}</code>
                                            <Button variant="outline" size="icon" className="size-8 shrink-0"
                                                onClick={async () => { try { await navigator.clipboard.writeText(secretKey); toast.success('Secret key copied.'); } catch { /* ignore */ } }}
                                                title="Copy secret key">
                                                <CopyIcon className="size-3.5" />
                                            </Button>
                                        </div>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Can&apos;t scan the QR code? Enter the secret key manually into your authenticator app.
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Step 2: Confirm the code</CardTitle>
                            <CardDescription>
                                Enter the 6-digit verification code from your authenticator app
                                to confirm that two-factor authentication is set up correctly.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex max-w-xs items-end gap-3">
                                <div className="flex-1">
                                    <Label htmlFor="two-factor-code">Verification code</Label>
                                    <Input id="two-factor-code" inputMode="numeric" autoComplete="one-time-code"
                                        maxLength={6} placeholder="000000" value={code}
                                        onChange={(e) => { setCode(e.target.value.replace(/\D/g, '')); if (codeError) setCodeError(null); }}
                                        disabled={codeProcessing} />
                                    {codeError ? <p className="mt-1 text-sm text-destructive">{codeError}</p> : null}
                                </div>
                                <Button onClick={handleConfirmCode} disabled={codeProcessing || code.length < 6}>
                                    {codeProcessing ? 'Confirming…' : 'Confirm'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* --- Enabled state --- */}
            {displayState === 'enabled' && (
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-full bg-primary/10">
                                    <ShieldCheckIcon className="size-5 text-primary" />
                                </div>
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <CardTitle>Two-factor authentication</CardTitle>
                                        <Badge variant="default" className="shrink-0">Enabled</Badge>
                                    </div>
                                    <CardDescription>Your account is protected with an extra layer of security.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <p className="text-sm text-muted-foreground">
                                Whenever you sign in, you&apos;ll need both your password and a
                                verification code from your authenticator app.
                            </p>
                            <div>
                                <Button variant="destructive" onClick={() => runGuarded(handleDisable)}>
                                    <ShieldOffIcon className="mr-2 size-4" />
                                    Disable two-factor authentication
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recovery codes</CardTitle>
                            <CardDescription>
                                Recovery codes allow you to access your account if you lose access
                                to your authenticator app. Each code can only be used once.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {!codesRevealed ? (
                                <Button variant="outline" onClick={fetchRecoveryCodes} disabled={codesLoading}>
                                    {codesLoading ? 'Loading…' : 'Show recovery codes'}
                                </Button>
                            ) : (
                                <div className="flex flex-col gap-4">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-xs text-muted-foreground">
                                            Save these codes in a secure location. You won&apos;t be
                                            able to see them again after closing this page.
                                        </p>
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" onClick={copyAllCodes} disabled={!recoveryCodes}>
                                                {copied ? <><CheckIcon className="mr-1.5 size-3.5" />Copied</> : <><CopyIcon className="mr-1.5 size-3.5" />Copy all</>}
                                            </Button>
                                            <Button variant="outline" size="sm" onClick={() => runGuarded(handleRegenerate)} disabled={regenerating}>
                                                {regenerating ? 'Regenerating…' : 'Regenerate'}
                                            </Button>
                                        </div>
                                    </div>
                                    {recoveryCodes && recoveryCodes.length > 0 && (
                                        <div className="grid grid-cols-2 gap-2">
                                            {recoveryCodes.map((rc, i) => (
                                                <button key={i} type="button"
                                                    className="flex items-center gap-2 rounded-md border bg-muted/50 px-3 py-2 text-left font-mono text-xs transition hover:bg-muted"
                                                    onClick={() => copySingleCode(rc)} title="Copy code">
                                                    <span className="text-muted-foreground">{String(i + 1).padStart(2, '0')}</span>
                                                    <code className="text-foreground">{rc}</code>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Password confirmation dialog */}
            <ConfirmPasswordDialog
                open={passwordDialogOpen}
                onOpenChange={(open) => { setPasswordDialogOpen(open); if (!open) pendingActionRef.current = null; }}
                onConfirmed={handlePasswordConfirmed}
            />
        </>
    );
}