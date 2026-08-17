<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Actions;

use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * First-party default for Fortify's ResetsUserPasswords contract
 * (docs/ROADMAP.md "1.9 auth pages") — the panel's reset-password flow works
 * out of the box, mirroring Filament's default reset behavior. Validation is
 * already enforced by Fortify's NewPasswordRequest before this runs, so the
 * action only writes the new password. A consumer binding their own action —
 * e.g. `Fortify::resetUserPasswordsUsing(TheirAction::class)` — always wins.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function reset(mixed $user, array $input): void
    {
        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
