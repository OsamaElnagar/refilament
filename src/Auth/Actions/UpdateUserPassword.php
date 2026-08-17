<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Actions;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * The package's first-party default for updating a user's password — mirrors
 * the official Laravel Fortify stub. Bound only when the consumer hasn't
 * already bound their own `UpdatesUserPasswords` contract, so a consumer's
 * `Fortify::updateUserPasswordUsing(...)` always wins.
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function update(mixed $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
