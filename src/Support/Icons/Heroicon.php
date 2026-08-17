<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Icons;

use Refilament\Refilament\Support\Contracts\ScalableIcon;
use Refilament\Refilament\Support\Enums\IconSize;

/**
 * A typed catalog of the icons the panel renderer understands (mirrors the
 * shape of Filament's `Heroicon` enum). Each case maps to a canonical key that
 * the React `ICONS` map in `resources/js/tables/cell.tsx` resolves to a lucide
 * component — so `Heroicon::Check->getIconForSize(...)` yields `'check'`, the
 * same key the renderer already consumes.
 *
 * Outlined cases (prefix `o-`, mirroring Filament's `Outlined*` variants) map
 * to the same canonical key as their plain counterpart, because lucide ships a
 * single glyph per name. The set below is the representative slice that covers
 * the renderer's known keys; the full heroicons catalog is a mechanical
 * follow-up.
 */
enum Heroicon: string implements ScalableIcon
{
    case Check = 'check';

    case CheckCircle = 'check-circle';

    case X = 'x';

    case XCircle = 'x-circle';

    case Globe = 'globe';

    case Mail = 'mail';

    case Phone = 'phone';

    case User = 'user';

    case Users = 'users';

    case Link = 'link';

    case Star = 'star';

    case Clock = 'clock';

    case Lock = 'lock';

    case Pencil = 'pencil';

    case Trash = 'trash';

    case MoreHorizontal = 'more-horizontal';

    case Archive = 'archive';

    case Eye = 'eye';

    case EyeOff = 'eye-off';

    case Pin = 'pin';

    case Alert = 'alert';

    case Tag = 'tag';

    case Plus = 'plus';

    case ChartBar = 'chart-bar';

    case Document = 'document';

    case ExternalLink = 'external-link';

    case Package = 'package';

    case Settings = 'settings';

    case OutlinedCheck = 'o-check';

    case OutlinedTrash = 'o-trash';

    case OutlinedXMark = 'o-x-mark';

    case OutlinedUser = 'o-user';

    public function getIconForSize(IconSize $size): string
    {
        // Outlined cases carry a `o-` prefix that only distinguishes a glyph
        // variant in real heroicons; lucide has one glyph per name, so the
        // canonical key is the stripped base name.
        if (str_starts_with($this->value, 'o-')) {
            return substr($this->value, 2);
        }

        return $this->value;
    }
}
