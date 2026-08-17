<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Tournament $tournament): bool
    {
        return $tournament->canShow($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tournament $tournament): bool
    {
        return $tournament->modifiableBy($user);
    }

    public function delete(User $user, Tournament $tournament): bool
    {
        return $tournament->modifiableBy($user);
    }
}
