import { useForm } from '@inertiajs/react';

import AuthLayout from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { panelUrl } from '@/lib/panel';

interface VerifyEmailProps {
    /** Flash status after a verification link was (re)sent. */
    status?: string;
}

/**
 * The panel's first-party email-verification prompt — `->emailVerification()`
 * arms it. Shown to verified-required visitors until their email is verified;
 * the "resend" button posts to Fortify's verification-notification endpoint,
 * and "log out" ends the session.
 */
export default function VerifyEmail({ status }: VerifyEmailProps) {
    const { post, processing } = useForm();

    return (
        <AuthLayout
            title="Verify your email address"
            description="Thanks for signing up! Before getting started, could you verify your email address by clicking the link we just emailed to you?"
        >
            {status ? (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            ) : null}

            <div className="flex flex-col gap-6">
                <Button
                    type="button"
                    className="w-full"
                    disabled={processing}
                    onClick={() => post(panelUrl('/email/verification-notification'))}
                >
                    {processing ? 'Sending…' : 'Resend verification email'}
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={() => post(panelUrl('/logout'))}
                >
                    Log out
                </Button>
            </div>
        </AuthLayout>
    );
}
