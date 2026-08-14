<?php

namespace App\Policies;

use App\Models\Fixture;
use App\Models\User;

class FixturePolicy
{
    public function update(User $user, Fixture $fixture): bool
    {
        return $fixture->tournament->modifiableBy($user);
    }
}
