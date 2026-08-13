<?php

namespace App\Http\Controllers;

use App\Backup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('backups/Index', [
            'backups' => collect(Backup::all())->map(function (array $backup) {
                $date = Carbon::createFromTimestamp($backup['timestamp'], config('app.timezone'));

                return [
                    'id' => $backup['id'],
                    'date' => $date->format('d.m.Y H:i'),
                    'filename' => $backup['filename'],
                    'age' => $date->diffForHumans(),
                    'size' => Number::fileSize(File::size(Backup::path($backup['filename'])), precision: 1),
                ];
            })->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $result = Backup::create();

        Inertia::flash('toast', match (true) {
            $result['success'] => ['type' => 'success', 'message' => __('Backup created.')],
            $result['skipped'] ?? false => ['type' => 'info', 'message' => __('No changes since the last backup.')],
            default => ['type' => 'error', 'message' => __('The backup could not be created.')]
        });

        return to_route('backups.index');
    }

    public function download(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->download($this->validatedPath($filename));
    }

    /**
     * Replace the database with the given backup, taking a safety backup first.
     */
    public function restore(Request $request, string $filename): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $this->validatedPath($filename);

        $success = Backup::restore($filename);

        Inertia::flash('toast', $success
            ? ['type' => 'success', 'message' => __('Backup restored.')]
            : ['type' => 'error', 'message' => __('The backup could not be restored.')]);

        return to_route('backups.index');
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        File::delete($this->validatedPath($filename));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup deleted.')]);

        return to_route('backups.index');
    }

    /**
     * The full path of the given backup, aborting if it is not an existing
     * backup file. Guards against path traversal in the filename parameter.
     */
    private function validatedPath(string $filename): string
    {
        abort_unless(collect(Backup::all())->contains('filename', $filename), 404);

        return Backup::path($filename);
    }
}
