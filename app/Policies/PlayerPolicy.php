<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Player $player): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Player $player): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Player $player): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Deleting a player who already appears in a tournament or team
        // would silently corrupt that historical data.
        return ! $player->isUsed();
    }
}
