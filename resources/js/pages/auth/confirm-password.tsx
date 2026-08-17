import { useForm } from '@inertiajs/react';

import AuthLayout from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { panelUrl } from '@/lib/panel';

/**
 * The fallback password-confirmation page — reached when the
 * `password.confirm` middleware redirects a browser that isn't running the
 * panel's JS client (e.g. a direct navigation to a guarded endpoint, or a
 * fetch that didn't use the JSON fallback). Renders a centered AuthLayout
 * card with a password form that POSTs to Fortify's confirm-password
 * endpoint and carries the `next` URL Fortify redirects to after success.
 */
export default function ConfirmPassword({ next }: { next: string }) {
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        next,
    });

    return (
        <AuthLayout
            title="Confirm password"
            description="For your security, please confirm your current password to continue."
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(panelUrl('/user/confirm-password'));
                }}
                className="flex flex-col gap-6"
            >
                <div className="grid gap-2">
                    <Label htmlFor="confirm-password">Password</Label>
                    <Input
                        id="confirm-password"
                        type="password"
                        name="password"
                        autoFocus
                        required
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password ? (
                        <p className="text-sm text-destructive">{errors.password}</p>
                    ) : null}
                </div>

                <input type="hidden" name="next" value={next} />

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? 'Confirming…' : 'Confirm'}
                </Button>
            </form>
        </AuthLayout>
    );
}