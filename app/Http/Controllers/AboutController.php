<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    /**
     * The page is public, like the tournaments index: the credits and the
     * contact address are meant to be readable without an account.
     *
     * The credits name the versions the application is built for, not the
     * patch release it happens to run on, so both are cut back: Laravel to
     * its major version, PHP to major and minor.
     */
    public function index(): Response
    {
        return Inertia::render('About', [
            'appName' => config('app.name'),
            'laravelVersion' => explode('.', app()->version())[0],
            'phpVersion' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        ]);
    }
}
