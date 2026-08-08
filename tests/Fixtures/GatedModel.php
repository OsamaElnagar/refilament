<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Fixture model for the slice 4.1 unit tests. Deliberately a plain Eloquent
 * model with no backing table — the authorization checks inspect policies and
 * never hit the database, and the class is dedicated to the fixture policy so
 * registering that policy cannot leak into any other test.
 */
class GatedModel extends Model
{
    /** @var string */
    protected $table = 'gated_models';

    /** Whether the fixture policy treats this record as locked (no delete). */
    public bool $locked = false;
}
