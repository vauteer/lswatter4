<?php

use App\Backup;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->backupDirectory = sys_get_temp_dir().'/backup-ui-test-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->backupDirectory);

    // A database name that certainly does not exist, so that mysqldump
    // deterministically fails when a test reaches the shell pipeline.
    config([
        'backup.directory' => $this->backupDirectory,
        'database.connections.mariadb.database' => 'backup_ui_test_missing_db',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->backupDirectory);
});

function createUiBackupFile(CarbonInterface $date, string $content = 'dump'): string
{
    $filename = 'backup_ui_test_missing_db_'.$date->format(Backup::DATE_FORMAT).'_utc.sql.gz';
    File::put(Backup::path($filename), $content);

    return $filename;
}

test('guests are redirected to login', function () {
    $this->get(route('backups.index'))->assertRedirect(route('login'));
});

test('non-admins may not view, create, restore or delete backups', function () {
    $user = User::factory()->create();
    $filename = createUiBackupFile(now()->subHour());

    $this->actingAs($user)->get(route('backups.index'))->assertForbidden();
    $this->actingAs($user)->post(route('backups.store'))->assertForbidden();
    $this->actingAs($user)->get(route('backups.download', ['filename' => $filename]))->assertForbidden();
    $this->actingAs($user)->post(route('backups.restore', ['filename' => $filename]))->assertForbidden();
    $this->actingAs($user)->delete(route('backups.destroy', ['filename' => $filename]))->assertForbidden();
});

test('the index lists backups newest first with age and size', function () {
    $this->actingAs(User::factory()->admin()->create());
    createUiBackupFile(now()->subDays(3));
    $newest = createUiBackupFile(now()->subHours(2), str_repeat('x', 2048));

    $this->get(route('backups.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('backups/Index')
            ->has('backups', 2)
            ->where('backups.0.filename', $newest)
            ->where('backups.0.age', 'vor 2 Stunden')
            ->where('backups.0.size', '2.0 KB')
            ->has('backups.0.date')
            ->missing('backups.0.timestamp'));
});

test('a backup can be downloaded', function () {
    $this->actingAs(User::factory()->admin()->create());
    $filename = createUiBackupFile(now()->subHour());

    $this->get(route('backups.download', ['filename' => $filename]))
        ->assertSuccessful()
        ->assertDownload($filename);
});

test('unknown or invalid filenames are rejected with 404', function (string $routeName, string $method) {
    $this->actingAs(User::factory()->admin()->create());
    createUiBackupFile(now()->subHour());

    $this->{$method}(route($routeName, ['filename' => 'unknown.sql.gz']))->assertNotFound();
    $this->{$method}(route($routeName, ['filename' => '.env']))->assertNotFound();
    $this->{$method}(route($routeName, [
        'filename' => 'backup_ui_test_missing_db_2020_01_01_00_00_00_utc.sql.gz',
    ]))->assertNotFound();
})->with([
    ['backups.download', 'get'],
    ['backups.restore', 'post'],
    ['backups.destroy', 'delete'],
]);

test('a backup can be deleted', function () {
    $this->actingAs(User::factory()->admin()->create());
    $filename = createUiBackupFile(now()->subHour());

    $this->delete(route('backups.destroy', ['filename' => $filename]))
        ->assertRedirect(route('backups.index'));

    expect(File::exists(Backup::path($filename)))->toBeFalse();
});

test('creating a backup is skipped with an info toast when nothing has changed', function () {
    $admin = User::factory()->admin()->create();
    // The factory-created admin's own row is timestamped "now", so it must
    // be backdated too, otherwise isDirty() sees it as the change.
    DB::table('users')->update(['updated_at' => now()->subWeek()]);
    $this->actingAs($admin);
    createUiBackupFile(now()->subDay());

    $this->post(route('backups.store'))
        ->assertRedirect(route('backups.index'));

    expect(session('inertia.flash_data')['toast'])->toBe([
        'type' => 'info',
        'message' => __('No changes since the last backup.'),
    ]);
    expect(File::glob($this->backupDirectory.'/*.sql.gz'))->toHaveCount(1);
});

test('creating a backup reports an error when the dump fails', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('backups.store'))
        ->assertRedirect(route('backups.index'));

    expect(File::glob($this->backupDirectory.'/*.sql.gz'))->toBeEmpty();
});

test('a restore is rejected for a corrupt backup file', function () {
    $this->actingAs(User::factory()->admin()->create());
    $filename = createUiBackupFile(now()->subHour(), 'not a gzip file');

    $this->post(route('backups.restore', ['filename' => $filename]))
        ->assertRedirect(route('backups.index'));

    expect(File::exists(Backup::path($filename)))->toBeTrue();
});

test('a restore is aborted when the safety backup cannot be created', function () {
    $this->actingAs(User::factory()->admin()->create());
    $filename = createUiBackupFile(now()->subHour(), (string) gzencode('-- valid sql dump'));

    $this->post(route('backups.restore', ['filename' => $filename]))
        ->assertRedirect(route('backups.index'));

    expect(File::glob($this->backupDirectory.'/*.sql.gz'))->toHaveCount(1);
});
