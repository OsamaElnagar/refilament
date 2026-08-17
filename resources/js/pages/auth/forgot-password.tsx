import { useForm } from '@inertiajs/react';

import AuthLayout from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { panelUrl } from '@/lib/panel';

interface ForgotPasswordProps {
    /** Flash status after a successful reset-link request. */
    status?: string;
}

/**
 * The panel's first-party forgot-password page — `->passwordReset()` arms it.
 * Posts to Fortify's password-email endpoint under the panel path; the reset
 * link (Laravel's password broker + notification) is emailed to the visitor.
 */
export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    return (
        <AuthLayout
            title="Forgot your password?"
            description="No problem. Just let us know your email address and we will email you a password reset link"
        >
            {status ? (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            ) : null}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(panelUrl('/forgot-password'));
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
                        autoFocus
                        required
                        placeholder="email@example.com"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email ? <p className="text-sm text-destructive">{errors.email}</p> : null}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? 'Emailing reset link…' : 'Email password reset link'}
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    Remembered your password?{' '}
                    <a
                        href={panelUrl('/login')}
                        className="font-medium text-foreground underline-offset-4 hover:underline"
                    >
                        Back to login
                    </a>
                </p>
            </form>
        </AuthLayout>
    );
}
