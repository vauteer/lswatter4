<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Deleting your own account here would lock you out without the
        // password confirmation the profile settings page requires.
        if ($user->is($model)) {
            return false;
        }

        // Deleting a user who already created a tournament would leave
        // that tournament without a creator - the database's foreign key
        // would reject it anyway, but this gives a friendlier error.
        return ! $model->hasTournaments();
    }
}
