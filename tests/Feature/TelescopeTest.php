<?php

use Illuminate\Support\Arr;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

/**
 * Runs the entry through the filter the TelescopeServiceProvider registered,
 * the same way Telescope::record() decides whether to keep an entry.
 *
 * Telescope::$filterUsing is static, so it collects one closure per
 * application the suite has booted - only the last one belongs to the
 * container that is still alive.
 */
function isRecorded(IncomingEntry $entry): bool
{
    $filter = Arr::last(Telescope::$filterUsing);

    return $filter($entry);
}

function requestEntry(int $status): IncomingEntry
{
    return IncomingEntry::make(['response_status' => $status])->type(EntryType::REQUEST);
}

test('a successful request is not recorded outside the local environment', function () {
    expect(isRecorded(requestEntry(200)))->toBeFalse();
});

test('a failing request is always recorded', function () {
    expect(isRecorded(requestEntry(500)))->toBeTrue();
});

test('a scheduled task is always recorded', function () {
    $entry = IncomingEntry::make([])->type(EntryType::SCHEDULED_TASK);

    expect(isRecorded($entry))->toBeTrue();
});

test('everything is recorded once record_everything is turned on', function () {
    config(['telescope.record_everything' => true]);

    expect(isRecorded(requestEntry(200)))->toBeTrue();
});
