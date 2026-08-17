import {
    Archive,
    BarChart3,
    Check,
    CircleCheck,
    CircleX,
    Clock,
    Eye,
    EyeOff,
    ExternalLink,
    FileText,
    Globe,
    Link2,
    Lock,
    Mail,
    MoreHorizontal,
    Package,
    Pencil,
    Phone,
    Pin,
    Plus,
    Settings,
    Star,
    Tag,
    Trash2,
    TriangleAlert,
    User,
    Users,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * The shared icon registry. The server emits a canonical key per record (see
 * the PHP `Heroicon` enum, `getIconForSize()`); this maps well-known keys to
 * lucide components and lets unknown ones drop out gracefully. This is the
 * single source of truth — cells, badges, notifications, actions, sidebar and
 * search all resolve icons through it.
 */
export const ICONS: Record<string, LucideIcon> = {
    check: Check,
    'check-circle': CircleCheck,
    x: X,
    'x-circle': CircleX,
    globe: Globe,
    mail: Mail,
    phone: Phone,
    user: User,
    users: Users,
    link: Link2,
    star: Star,
    clock: Clock,
    lock: Lock,
    pencil: Pencil,
    trash: Trash2,
    'more-horizontal': MoreHorizontal,
    archive: Archive,
    eye: Eye,
    'eye-off': EyeOff,
    pin: Pin,
    alert: TriangleAlert,
    tag: Tag,
    plus: Plus,
    'chart-bar': BarChart3,
    document: FileText,
    'external-link': ExternalLink,
    package: Package,
    settings: Settings,
};

/** Tailwind size classes per named icon size, mirroring Filament's `IconSize`. */
const ICON_SIZES = {
    xs: 'size-3',
    sm: 'size-3.5',
    md: 'size-4',
    lg: 'size-5',
    xl: 'size-6',
    '2xl': 'size-8',
} as const;

export type IconSize = keyof typeof ICON_SIZES;

interface IconProps {
    name: string;
    size?: IconSize;
    className?: string;
}

/**
 * Render a named icon through the shared registry. Outlined heroicon keys
 * (`o-foo`) share a single glyph with their plain counterpart in lucide, so
 * the `o-` prefix is normalized away — matching `Heroicon::getIconForSize()`.
 * Unknown keys render nothing. `className` overrides the default size via
 * tailwind-merge.
 */
export function Icon({ name, size = 'md', className }: IconProps) {
    const Component = ICONS[name.replace(/^o-/, '')];

    if (!Component) {
        return null;
    }

    return <Component className={cn(ICON_SIZES[size], className)} aria-hidden="true" />;
}