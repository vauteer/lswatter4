<?php

use Illuminate\Support\Facades\Storage;

test('the command uploads, verifies, and cleans up a test file', function () {
    Storage::fake('s3');

    $this->artisan('aws:test')
        ->expectsOutputToContain('✓ Successfully uploaded test file to:')
        ->expectsOutputToContain('✓ Successfully read the test file')
        ->expectsOutputToContain('✓ Successfully deleted test file')
        ->expectsOutputToContain('AWS S3 test completed!')
        ->assertSuccessful();

    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});
