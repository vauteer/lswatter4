<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\LogFile;

test('guests cannot access the log viewer', function () {
    $this->get('/log-viewer')->assertRedirect(route('login'));
});

test('non-admins cannot access the log viewer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/log-viewer')->assertForbidden();
});

test('admins can access the log viewer', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/log-viewer')->assertOk();
});

test('the log viewer lists only the application log', function () {
    $applicationLog = storage_path('logs/laravel.log');
    $serverLog = storage_path('logs/php-fpm.log');
    $applicationLogExisted = File::exists($applicationLog);
    $line = '[2026-08-24 10:00:00] production.INFO: test'.PHP_EOL;

    try {
        File::put($serverLog, $line);

        if (! $applicationLogExisted) {
            File::put($applicationLog, $line);
        }

        $names = LogViewer::getFiles()->map(fn (LogFile $file) => $file->name)->all();

        expect($names)->toContain('laravel.log')
            ->and($names)->not->toContain('php-fpm.log');
    } finally {
        File::delete($serverLog);

        if (! $applicationLogExisted) {
            File::delete($applicationLog);
        }
    }
});
