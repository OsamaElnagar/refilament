import { useForm } from '@inertiajs/react';
import { useState } from 'react';

import AuthLayout from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { panelUrl } from '@/lib/panel';

/**
 * The panel's first-party two-factor challenge — shown when a login hits an
 * account with two-factor enabled (Fortify's `RedirectsIfTwoFactorAuthenticatable`
 * bounces the visitor here after entering their password). Posts the
 * authenticator code (or a recovery code) to Fortify's challenge endpoint
 * under the panel path.
 */
export default function TwoFactorChallenge() {
    const [usingRecoveryCode, setUsingRecoveryCode] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        recovery_code: '',
    });

    return (
        <AuthLayout
            title="Two factor authentication"
            description={
                usingRecoveryCode
                    ? 'Confirm access with one of your recovery codes'
                    : 'Confirm access with the code from your authenticator app'
            }
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(panelUrl('/two-factor-challenge'));
                }}
                className="flex flex-col gap-6"
            >
                {usingRecoveryCode ? (
                    <div className="grid gap-2">
                        <Label htmlFor="recovery_code">Recovery code</Label>
                        <Input
                            id="recovery_code"
                            name="recovery_code"
                            autoFocus
                            required
                            autoComplete="one-time-code"
                            value={data.recovery_code}
                            onChange={(event) => setData('recovery_code', event.target.value)}
                        />
                        {errors.recovery_code ? (
                            <p className="text-sm text-destructive">{errors.recovery_code}</p>
                        ) : null}
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="code">Authentication code</Label>
                        <Input
                            id="code"
                            name="code"
                            autoFocus
                            required
                            autoComplete="one-time-code"
                            inputMode="numeric"
                            value={data.code}
                            onChange={(event) => setData('code', event.target.value)}
                        />
                        {errors.code ? (
                            <p className="text-sm text-destructive">{errors.code}</p>
                        ) : null}
                    </div>
                )}

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? 'Confirming…' : 'Confirm'}
                </Button>

                <button
                    type="button"
                    className="text-center text-sm text-muted-foreground underline-offset-4 hover:underline"
                    onClick={() => setUsingRecoveryCode((using) => !using)}
                >
                    {usingRecoveryCode ? 'Use an authentication code' : 'Use a recovery code'}
                </button>
            </form>
        </AuthLayout>
    );
}
