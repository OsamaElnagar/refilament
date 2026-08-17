import { useState } from 'react';
import { UserIcon, KeyRoundIcon } from 'lucide-react';
import { toast } from 'sonner';

import TwoFactorSection from '@/components/auth/TwoFactorSection';
import AppShell from '@/components/shell/AppShell';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';

interface EditProfileProps {
    /** The authenticated user's name (server pre-fill). */
    name: string;
    /** The authenticated user's email (server pre-fill). */
    email: string;
    /**
     * Two-factor authentication state, present only when the panel has
     * `->twoFactorAuthentication()` enabled — the React page embeds the
     * full 2FA section when this prop is available.
     */
    twoFactor?: { enabled: boolean; enabling: boolean } | null;
}

/**
 * The panel's profile page (Filament's `->profile()`) — EditProfile with
 * Profile Information (name/email), Update Password, and (when 2FA is
 * available) the Two-factor authentication management section.
 *
 * Renders inside the panel shell (AppShell), uses plain fetch + CSRF for
 * the profile/password PUT endpoints (same pattern as the 2FA page), and
 * clears password fields on success. All three cards are laid out vertically
 * with a separator between them.
 */
export default function EditProfile({ name, email, twoFactor }: EditProfileProps) {
    // Profile information state.
    const [profileName, setProfileName] = useState(name);
    const [profileEmail, setProfileEmail] = useState(email);
    const [profileProcessing, setProfileProcessing] = useState(false);
    const [profileErrors, setProfileErrors] = useState<Record<string, string>>({});

    // Password update state.
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');
    const [passwordProcessing, setPasswordProcessing] = useState(false);
    const [passwordErrors, setPasswordErrors] = useState<Record<string, string>>({});

    // --- Update profile information ---
    const handleUpdateProfile = async (e: React.FormEvent) => {
        e.preventDefault();
        setProfileProcessing(true);
        setProfileErrors({});

        try {
            const res = await fetch(panelUrl('/user/profile-information'), {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken() ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ name: profileName, email: profileEmail }),
            });

            if (res.ok) {
                toast.success('Profile updated successfully.');
            } else if (res.status === 422) {
                const body = await res.json().catch(() => null);
                const errors = body?.errors ?? {};
                const flat: Record<string, string> = {};
                for (const [key, msgs] of Object.entries(errors)) {
                    flat[key] = (msgs as string[])[0];
                }
                setProfileErrors(flat);
            } else {
                toast.error('Failed to update profile.');
            }
        } catch {
            toast.error('An unexpected error occurred.');
        } finally {
            setProfileProcessing(false);
        }
    };

    // --- Update password ---
    const handleUpdatePassword = async (e: React.FormEvent) => {
        e.preventDefault();
        setPasswordProcessing(true);
        setPasswordErrors({});

        try {
            const res = await fetch(panelUrl('/user/password'), {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken() ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    password: newPassword,
                    password_confirmation: newPasswordConfirmation,
                }),
            });

            if (res.ok) {
                toast.success('Password updated successfully.');
                setCurrentPassword('');
                setNewPassword('');
                setNewPasswordConfirmation('');
            } else if (res.status === 422) {
                const body = await res.json().catch(() => null);
                const errors = body?.errors ?? {};
                const flat: Record<string, string> = {};
                for (const [key, msgs] of Object.entries(errors)) {
                    flat[key] = (msgs as string[])[0];
                }
                setPasswordErrors(flat);
            } else {
                toast.error('Failed to update password.');
            }
        } catch {
            toast.error('An unexpected error occurred.');
        } finally {
            setPasswordProcessing(false);
        }
    };

    return (
        <AppShell>
            <main className="mx-auto w-full max-w-3xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">Profile</h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Manage your account information and security settings.
                    </p>
                </header>

                <Separator className="mb-8" />

                {/* Profile Information */}
                <Card className="mb-6">
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                                <UserIcon className="size-5 text-muted-foreground" />
                            </div>
                            <div>
                                <CardTitle>Profile information</CardTitle>
                                <CardDescription>
                                    Update your name and email address.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleUpdateProfile} className="flex flex-col gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="profile-name">Name</Label>
                                <Input
                                    id="profile-name"
                                    value={profileName}
                                    onChange={(e) => setProfileName(e.target.value)}
                                    disabled={profileProcessing}
                                    required
                                />
                                {profileErrors.name ? (
                                    <p className="text-sm text-destructive">{profileErrors.name}</p>
                                ) : null}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="profile-email">Email</Label>
                                <Input
                                    id="profile-email"
                                    type="email"
                                    value={profileEmail}
                                    onChange={(e) => setProfileEmail(e.target.value)}
                                    disabled={profileProcessing}
                                    required
                                />
                                {profileErrors.email ? (
                                    <p className="text-sm text-destructive">{profileErrors.email}</p>
                                ) : null}
                            </div>
                            <div>
                                <Button type="submit" disabled={profileProcessing}>
                                    {profileProcessing ? 'Saving…' : 'Save'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Update Password */}
                <Card className="mb-6">
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                                <KeyRoundIcon className="size-5 text-muted-foreground" />
                            </div>
                            <div>
                                <CardTitle>Update password</CardTitle>
                                <CardDescription>
                                    Ensure your account is using a strong, unique password.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleUpdatePassword} className="flex flex-col gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="current-password">Current password</Label>
                                <Input
                                    id="current-password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={currentPassword}
                                    onChange={(e) => setCurrentPassword(e.target.value)}
                                    disabled={passwordProcessing}
                                    required
                                />
                                {passwordErrors.current_password ? (
                                    <p className="text-sm text-destructive">{passwordErrors.current_password}</p>
                                ) : null}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="new-password">New password</Label>
                                <Input
                                    id="new-password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={newPassword}
                                    onChange={(e) => setNewPassword(e.target.value)}
                                    disabled={passwordProcessing}
                                    required
                                />
                                {passwordErrors.password ? (
                                    <p className="text-sm text-destructive">{passwordErrors.password}</p>
                                ) : null}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="new-password-confirmation">Confirm new password</Label>
                                <Input
                                    id="new-password-confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={newPasswordConfirmation}
                                    onChange={(e) => setNewPasswordConfirmation(e.target.value)}
                                    disabled={passwordProcessing}
                                    required
                                />
                            </div>
                            <div>
                                <Button type="submit" disabled={passwordProcessing}>
                                    {passwordProcessing ? 'Updating…' : 'Update password'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Two-factor authentication section (only when 2FA is enabled) */}
                {twoFactor && (
                    <div className="mb-6">
                        <TwoFactorSection enabled={twoFactor.enabled} enabling={twoFactor.enabling} />
                    </div>
                )}
            </main>
        </AppShell>
    );
}