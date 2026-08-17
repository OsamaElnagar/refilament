<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Actions;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * The package's first-party default for updating a user's profile information
 * (name, email) — mirrors the official Laravel Fortify stub. Bound only when
 * the consumer hasn't already bound their own `UpdatesUserProfileInformation`
 * contract, so a consumer's `Fortify::updateUserProfileInformationUsing(...)`
 * always wins (same pattern as the `CreatesNewUsers` / `ResetsUserPasswords`
 * defaults).
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function update(mixed $user, array $input): void
    {
        /** @var User $user */
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        /** @phpstan-ignore-next-line Access to Eloquent model attribute */
        if ($input['email'] !== $user->email
            && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);

            return;
        }

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
        ])->save();
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    protected function updateVerifiedUser(mixed $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
