import { CheckIcon, LaptopIcon, MoonIcon, SunIcon } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';

/**
 * The panel appearance toggle (slice 4.2 — docs/ROADMAP.md "4.2 Dark mode + theme
 * polish"). A dropdown offering Light / Dark / System, mounted in the AppShell
 * header. The trigger icon cross-fades to reflect the *resolved* appearance,
 * and the menu's check marks the explicit selection.
 *
 * All theme application + persistence lives in the shared use-appearance hook
 * (localStorage + cookie + `dark` class on <html>); this component is a thin
 * control over it. SSR-safe: the hook never touches window/document during the
 * server render.
 */
const OPTIONS: { appearance: Appearance; icon: typeof SunIcon; label: string }[] = [
    { appearance: 'light', icon: SunIcon, label: 'Light' },
    { appearance: 'dark', icon: MoonIcon, label: 'Dark' },
    { appearance: 'system', icon: LaptopIcon, label: 'System' },
];

export default function ThemeToggle() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button variant="ghost" size="icon" className="relative size-9">
                        <SunIcon className="size-4 scale-100 rotate-0 transition-all dark:scale-0 dark:rotate-90" />
                        <MoonIcon className="absolute size-4 scale-0 rotate-90 transition-all dark:scale-100 dark:rotate-0" />
                        <span className="sr-only">Toggle theme</span>
                    </Button>
                }
            />

            <DropdownMenuContent align="end" className="w-40">
                <DropdownMenuLabel>Appearance</DropdownMenuLabel>
                <DropdownMenuSeparator />

                {OPTIONS.map(({ appearance: option, icon: Icon, label }) => (
                    <DropdownMenuItem key={option} onClick={() => updateAppearance(option)}>
                        <Icon className="size-4" />
                        {label}
                        {appearance === option ? <CheckIcon className="ml-auto size-4" /> : null}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}