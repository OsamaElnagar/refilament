<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Actions;

use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * First-party default for Fortify's CreatesNewUsers contract (docs/ROADMAP.md
 * "1.9 auth pages") — the panel's registration page works out of the box,
 * mirroring Filament's default registration action. The user is created
 * through the auth provider's model (the same model the panel's guard
 * authenticates), so a fresh install needs no model changes. A consumer who
 * binds their own action — e.g. `Fortify::createUsersUsing(TheirAction::class)`
 * — always wins: the package only binds this when the contract is unbound.
 */
class CreateNewUser implements CreatesNewUsers
{
    /**
     * Create a new user from the registration request's validated input.
     * Hashing is explicit so the default works whether or not the consumer's
     * model carries Laravel's `hashed` password cast.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): mixed
    {
        /** @var class-string $model */
        $model = config('auth.providers.users.model', 'App\\Models\\User');

        return $model::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
