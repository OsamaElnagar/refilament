import { Link, router, usePage } from '@inertiajs/react';
import { ChevronsUpDown, KeyRound, LogOut, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';

import type { PanelConfig } from '@/components/shell/PanelSidebar';
import { SHELL_SLOTS, ShellSlot } from '@/components/shell/ShellSlots';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
/**
 * The authenticated user the server shares under `props.refilament.user`
 * (slice 1.9 "user menu") — name/email resolved per request from the panel's
 * auth guard. Absent entirely for guests, so the menu simply doesn't render.
 */
interface PanelUser {
    name: string;
    email: string;
}

/** The initials for the avatar fallback — 'Osama Elrefaei' → 'OE'. */
function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

/**
 * The shell's user menu (slice 1.9 — the React analogue of Filament's user
 * dropdown in the topbar): the authenticated user's initials + name open a
 * menu with the panel's account pages — Profile (Filament's `->profile()`)
 * and Two-factor settings — plus Logout. Each link is rendered only when the
 * server shared its URL (a panel that didn't enable `->profile()` has no
 * profileUrl, so no Profile item), and logout POSTs to the panel's logout
 * route, landing back on the login page via the panel's LogoutResponse.
 */
export default function UserMenu(): ReactNode | null {
    const { props } = usePage();
    const refilament = (props as {
        refilament?: {
            panel?: PanelConfig;
            user?: PanelUser;
        };
    }).refilament;

    const user = refilament?.user;
    const panel = refilament?.panel;

    // The menu needs both a user to greet and a logout route to act on. A
    // panel with an auth gate but no auth pages has no logout route, so the
    // menu disappears there too — the same gate the server applies to
    // `logoutUrl` itself.
    if (!user || !panel?.logoutUrl) {
        return null;
    }

    const { logoutUrl } = panel;

    const logout = (): void => {
        router.post(logoutUrl, {}, { preserveScroll: true });
    };

    return (
        <div className="flex items-center">
            <ShellSlot name={SHELL_SLOTS.userMenuBefore} />
            <DropdownMenu>
                <DropdownMenuTrigger
                render={
                    <button
                        type="button"
                        aria-label={`Open ${user.name}'s menu`}
                        className="flex size-9 shrink-0 items-center justify-center gap-2 rounded-full text-sm font-medium transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                            {initials(user.name)}
                        </span>
                        <ChevronsUpDown className="hidden size-4 text-muted-foreground sm:block" aria-hidden="true" />
                    </button>
                }
            />

            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="flex flex-col gap-0.5">
                        <span className="truncate text-sm font-medium">{user.name}</span>
                        <span className="truncate text-xs font-normal text-muted-foreground">{user.email}</span>
                    </DropdownMenuLabel>

                    <DropdownMenuSeparator />

                    {panel.profileUrl ? (
                        <DropdownMenuItem
                            render={
                                <Link href={panel.profileUrl} className="flex items-center gap-2">
                                    <UserRound aria-hidden="true" />
                                    Profile
                                </Link>
                            }
                        />
                    ) : null}

                    {panel.twoFactorUrl ? (
                        <DropdownMenuItem
                            render={
                                <Link href={panel.twoFactorUrl} className="flex items-center gap-2">
                                    <KeyRound aria-hidden="true" />
                                    Two-factor authentication
                                </Link>
                            }
                        />
                    ) : null}
                </DropdownMenuGroup>

                <DropdownMenuSeparator />

                <DropdownMenuItem
                    variant="destructive"
                    onClick={logout}
                    className="flex items-center gap-2"
                >
                    <LogOut aria-hidden="true" />
                    Log out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        </div>
    );
}
