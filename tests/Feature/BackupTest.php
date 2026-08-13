<?php

use App\Backup;
use App\Models\Player;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupDirectory = sys_get_temp_dir().'/backup-test-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->backupDirectory);

    config([
        'backup.directory' => $this->backupDirectory,
        'database.connections.mariadb.database' => 'testdb',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->backupDirectory);
});

function createBackupFile(CarbonInterface $date): string
{
    $filename = 'testdb_'.$date->format(Backup::DATE_FORMAT).'.sql.gz';
    File::put(Backup::path($filename), 'dump');

    return $filename;
}

test('the filename is composed of the database prefix and a timestamp', function () {
    Carbon::setTestNow('2026-07-15 09:30:00');

    expect(Backup::prefix())->toBe('testdb_')
        ->and(Backup::makeFilename())->toBe('testdb_2026_07_15_09_30_00.sql.gz');
});

test('path resolves inside the configured backup directory', function () {
    expect(Backup::path())->toBe($this->backupDirectory)
        ->and(Backup::path('foo.sql.gz'))->toBe($this->backupDirectory.'/foo.sql.gz');
});

test('all lists backups newest first and ignores foreign files', function () {
    $oldest = createBackupFile(now()->subDays(3));
    $newest = createBackupFile(now()->subHours(2));
    File::put(Backup::path('otherdb_2026_01_01_00_00_00.sql.gz'), 'foreign');
    File::put(Backup::path('testdb_not_a_date.sql.gz'), 'invalid');

    $backups = Backup::all();

    expect($backups)->toHaveCount(2)
        ->and($backups[0]['filename'])->toBe($newest)
        ->and($backups[0]['id'])->toBe(1)
        ->and($backups[0]['age'])->toBeGreaterThanOrEqual(119)
        ->and($backups[1]['filename'])->toBe($oldest);
});

test('latest returns the newest backup and null without backups', function () {
    expect(Backup::latest())->toBeNull()
        ->and(Backup::latestDate())->toBeNull();

    createBackupFile(now()->subDays(3));
    createBackupFile($newestDate = now()->subMinutes(30));

    expect(Backup::latestDate())->toBe($newestDate->format('Y-m-d H:i:s'));
});

test('deleteOld removes only backups older than the given days', function () {
    $old = createBackupFile(now()->subDays(200));
    $recent = createBackupFile(now()->subDays(10));
    $newest = createBackupFile(now()->subHours(1));

    $deleted = Backup::deleteOld(180, 1);

    expect($deleted)->toBe(1)
        ->and(File::exists(Backup::path($old)))->toBeFalse()
        ->and(File::exists(Backup::path($recent)))->toBeTrue()
        ->and(File::exists(Backup::path($newest)))->toBeTrue();
});

test('deleteOld always keeps the newest backups regardless of age', function () {
    foreach (range(1, 5) as $i) {
        createBackupFile(now()->subDays(300 + $i));
    }

    $deleted = Backup::deleteOld(180, 3);

    expect($deleted)->toBe(2)
        ->and(Backup::all())->toHaveCount(3);
});

test('deleteOld uses the configured retention defaults', function () {
    config(['backup.retain_days' => 30, 'backup.retain_count' => 1]);
    createBackupFile(now()->subDays(60));
    createBackupFile(now()->subDays(40));
    createBackupFile(now()->subHours(1));

    expect(Backup::deleteOld())->toBe(2)
        ->and(Backup::all())->toHaveCount(1);
});

test('the database is dirty without any backup', function () {
    expect(Backup::isDirty())->toBeTrue();
});

test('the database is clean when a backup exists and nothing has changed', function () {
    DB::table('players')->update(['updated_at' => now()->subWeek()]);
    createBackupFile(now()->subDays(2));

    expect(Backup::isDirty())->toBeFalse();
});

test('the database is dirty when a player is newer than the latest backup', function () {
    createBackupFile(now()->subDays(2));
    Player::factory()->create();

    expect(Backup::isDirty())->toBeTrue();
});

test('the dump command keeps every table structure but excludes operational table data', function () {
    config([
        'backup.exclude_tables' => ['cache', 'sessions'],
        'backup.mysqldump_binary' => 'mysqldump',
    ]);

    $method = new ReflectionMethod(Backup::class, 'buildDumpCommand');
    $command = $method->invoke(null, '/tmp/dump.sql.gz', '/tmp/creds.cnf');

    expect($command)
        ->toContain("--no-data 'testdb'")
        ->toContain("--no-create-info '--ignore-table=testdb.cache'")
        ->toContain('--ignore-table=testdb.sessions')
        ->and(substr_count($command, '--ignore-table='))->toBe(2)
        ->and($command)->toStartWith("{ 'mysqldump'")
        ->and($command)->toEndWith("testdb'; } | gzip -c > '/tmp/dump.sql.gz'")
        ->and(substr_count($command, 'mysqldump'))->toBe(2);
});

test('the dump command omits ignore-table flags when nothing is excluded', function () {
    config(['backup.exclude_tables' => [], 'backup.mysqldump_binary' => 'mysqldump']);

    $method = new ReflectionMethod(Backup::class, 'buildDumpCommand');
    $command = $method->invoke(null, '/tmp/dump.sql.gz', '/tmp/creds.cnf');

    expect($command)->not->toContain('--ignore-table');
});

test('the dump command uses the configured mysqldump binary', function () {
    config(['backup.mysqldump_binary' => '/opt/herd/bin/mysqldump']);

    $method = new ReflectionMethod(Backup::class, 'buildDumpCommand');
    $command = $method->invoke(null, '/tmp/dump.sql.gz', '/tmp/creds.cnf');

    expect(substr_count($command, "'/opt/herd/bin/mysqldump'"))->toBe(2)
        ->and($command)->not->toContain(' mysqldump ');
});

test('the restore command uses the configured mysql binary', function () {
    config(['backup.mysql_binary' => '/opt/herd/bin/mysql']);

    $method = new ReflectionMethod(Backup::class, 'buildRestoreCommand');
    $command = $method->invoke(null, '/tmp/dump.sql.gz', '/tmp/creds.cnf');

    expect($command)
        ->toContain("| '/opt/herd/bin/mysql' --defaults-extra-file='/tmp/creds.cnf' 'testdb'")
        ->toStartWith("gunzip -c '/tmp/dump.sql.gz' |");
});

test('the binaries auto-discover from the Herd bin directory when PATH lacks it and nothing is configured', function () {
    config(['backup.mysql_binary' => null, 'backup.mysqldump_binary' => null]);

    $fakeHome = sys_get_temp_dir().'/backup-home-test-'.bin2hex(random_bytes(6));
    $herdBin = $fakeHome.'/Library/Application Support/Herd/bin';
    File::ensureDirectoryExists($herdBin);
    File::put($herdBin.'/mysql', "#!/bin/sh\n");
    File::put($herdBin.'/mysqldump', "#!/bin/sh\n");
    chmod($herdBin.'/mysql', 0755);
    chmod($herdBin.'/mysqldump', 0755);

    // An empty directory, not a real system PATH: some CI images ship a real
    // mysql/mysqldump under /usr/bin, which would resolve before ever
    // reaching the Herd fallback below.
    $emptyPathDir = sys_get_temp_dir().'/backup-empty-path-test-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($emptyPathDir);

    $originalHome = getenv('HOME');
    $originalPath = getenv('PATH');

    try {
        putenv("HOME={$fakeHome}");
        putenv("PATH={$emptyPathDir}");

        $dumpCommand = (new ReflectionMethod(Backup::class, 'buildDumpCommand'))
            ->invoke(null, '/tmp/dump.sql.gz', '/tmp/creds.cnf');
        $restoreCommand = (new ReflectionMethod(Backup::class, 'buildRestoreCommand'))
            ->invoke(null, '/tmp/dump.sql.gz', '/tmp/creds.cnf');

        expect($dumpCommand)->toContain(escapeshellarg("{$herdBin}/mysqldump"))
            ->and($restoreCommand)->toContain(escapeshellarg("{$herdBin}/mysql"));
    } finally {
        putenv($originalHome === false ? 'HOME' : "HOME={$originalHome}");
        putenv($originalPath === false ? 'PATH' : "PATH={$originalPath}");
        File::deleteDirectory($fakeHome);
        File::deleteDirectory($emptyPathDir);
    }
});
