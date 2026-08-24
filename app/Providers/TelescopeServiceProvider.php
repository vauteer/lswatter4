<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(fn (IncomingEntry $entry): bool => $this->shouldRecord($entry));
    }

    /**
     * Outside the local environment only entries pointing at a problem are
     * kept, so a healthy site records next to nothing. Setting
     * TELESCOPE_RECORD_EVERYTHING records all traffic there as well, which
     * is read per entry so the switch takes effect without a redeploy.
     */
    protected function shouldRecord(IncomingEntry $entry): bool
    {
        return $this->app->environment('local')
            || (bool) config('telescope.record_everything', false)
            || $entry->isReportableException()
            || $entry->isFailedRequest()
            || $entry->isFailedJob()
            || $entry->isScheduledTask()
            || $entry->hasMonitoredTag();
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            return $user->isAdmin();
        });
    }
}
