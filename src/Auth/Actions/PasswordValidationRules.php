<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Actions;

/**
 * Shared password validation rules for the package's first-party auth actions
 * — mirrors the Laravel Fortify defaults (min:8, must match confirmation).
 */
trait PasswordValidationRules
{
    /** @return array<int, string> */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'confirmed', 'min:8'];
    }
}
