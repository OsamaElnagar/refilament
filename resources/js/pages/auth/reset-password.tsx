import { useForm } from '@inertiajs/react';

import AuthLayout from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { panelUrl } from '@/lib/panel';

interface ResetPasswordProps {
    /** The password-broker token from the emailed reset link. */
    token: string;
    /** The email the reset link was sent to (query param on the link). */
    email?: string;
    /** Flash status after a successful reset. */
    status?: string;
}

/**
 * The panel's first-party reset-password page — the second half of
 * `->passwordReset()`. Posts the token + email + new password to Fortify's
 * password-update endpoint under the panel path.
 */
export default function ResetPassword({ token, email = '', status }: ResetPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout
            title="Reset your password"
            description="Enter a new password for your account"
        >
            {status ? (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            ) : null}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(panelUrl('/reset-password'));
                }}
                className="flex flex-col gap-6"
            >
                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autoComplete="email"
                        required
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email ? <p className="text-sm text-destructive">{errors.email}</p> : null}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autoComplete="new-password"
                        required
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    {errors.password ? (
                        <p className="text-sm text-destructive">{errors.password}</p>
                    ) : null}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm new password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autoComplete="new-password"
                        required
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                    />
                    {errors.password_confirmation ? (
                        <p className="text-sm text-destructive">{errors.password_confirmation}</p>
                    ) : null}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? 'Resetting password…' : 'Reset password'}
                </Button>
            </form>
        </AuthLayout>
    );
}
