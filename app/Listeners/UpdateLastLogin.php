<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateLastLogin
{
    /**
     * Record the login time, unless it's an impersonation swap rather than
     * a real, credential-based login.
     */
    public function handle(Login $event): void
    {
        if (session()->has('impersonator_id')) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
