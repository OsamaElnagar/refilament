import { useForm } from '@inertiajs/react';

import AuthLayout from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { panelUrl } from '@/lib/panel';

interface LoginProps {
    /** Whether the panel's forgot-password flow is enabled (server-computed). */
    canResetPassword: boolean;
}

/**
 * The panel's first-party login page (docs/ROADMAP.md "1.9 auth pages"),
 * rendered by the `auth/login` Inertia component. Posts to Fortify's login
 * endpoint under the panel path; Fortify's pipeline (rate limiting, two-factor
 * challenge) runs server-side and redirects to the panel dashboard on success.
 */
export default function Login({ canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    return (
        <AuthLayout
            title="Log in"
            description="Enter your credentials to access the panel"
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(panelUrl('/login'));
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

                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword ? (
                            <a
                                href={panelUrl('/forgot-password')}
                                className="ml-auto text-sm text-muted-foreground underline-offset-4 hover:underline"
                            >
                                Forgot your password?
                            </a>
                        ) : null}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autoComplete="current-password"
                        required
                        placeholder="Password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    {errors.password ? (
                        <p className="text-sm text-destructive">{errors.password}</p>
                    ) : null}
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="remember"
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked === true)}
                    />
                    <Label htmlFor="remember" className="text-sm font-normal">
                        Remember me
                    </Label>
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? 'Logging in…' : 'Log in'}
                </Button>
            </form>
        </AuthLayout>
    );
}
