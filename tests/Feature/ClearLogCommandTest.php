<?php

use Illuminate\Support\Facades\File;

test('the command empties the log file', function () {
    $path = storage_path('logs/laravel.log');
    $original = File::exists($path) ? File::get($path) : null;
    File::put($path, "some previous log content\n");

    try {
        $this->artisan('app:clear-log')
            ->expectsOutputToContain('Log cleared')
            ->assertSuccessful();

        expect(File::get($path))->toBe('');
    } finally {
        $original === null ? File::delete($path) : File::put($path, $original);
    }
});
