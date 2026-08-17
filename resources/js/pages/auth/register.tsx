import { useForm } from '@inertiajs/react';

import AuthLayout from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { panelUrl } from '@/lib/panel';

/**
 * The panel's first-party registration page — `->registration()` arms it.
 * Posts to Fortify's register endpoint under the panel path; Fortify's
 * `createUsersUsing` action (the consumer's own) creates the account and
 * authenticates the visitor.
 */
export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout
            title="Create an account"
            description="Enter your details below to create your account"
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(panelUrl('/register'));
                }}
                className="flex flex-col gap-6"
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        autoComplete="name"
                        autoFocus
                        required
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                    />
                    {errors.name ? <p className="text-sm text-destructive">{errors.name}</p> : null}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autoComplete="email"
                        required
                        placeholder="email@example.com"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email ? <p className="text-sm text-destructive">{errors.email}</p> : null}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
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
                    <Label htmlFor="password_confirmation">Confirm password</Label>
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
                    {processing ? 'Creating account…' : 'Create account'}
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <a
                        href={panelUrl('/login')}
                        className="font-medium text-foreground underline-offset-4 hover:underline"
                    >
                        Log in
                    </a>
                </p>
            </form>
        </AuthLayout>
    );
}
