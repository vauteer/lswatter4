<?php

namespace App\Http\Controllers;

use App\Backup;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How stale the most recent backup may be before it's flagged, given
     * the daily 23:15 backup schedule (see routes/console.php).
     */
    private const int BACKUP_STALE_AFTER_HOURS = 26;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        $lastBackup = collect(Backup::all())->first();

        return Inertia::render('Dashboard', [
            'duplicatePlayerCount' => Player::possibleDuplicateGroups()->count(),
            'users' => [
                'total' => User::count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
                'recent' => User::orderByDesc('created_at')
                    ->limit(5)
                    ->get(['id', 'name', 'email', 'email_verified_at', 'created_at'])
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'verified' => $user->email_verified_at !== null,
                        'createdAgo' => $user->created_at->diffForHumans(),
                    ]),
            ],
            'lastBackup' => $lastBackup === null ? null : [
                'date' => Carbon::createFromTimestamp($lastBackup['timestamp'], config('app.timezone'))->format('d.m.Y H:i'),
                'ago' => Carbon::createFromTimestamp($lastBackup['timestamp'], config('app.timezone'))->diffForHumans(),
                'stale' => $lastBackup['age'] > self::BACKUP_STALE_AFTER_HOURS * 60,
            ],
        ]);
    }
}
