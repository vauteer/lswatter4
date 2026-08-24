<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    /**
     * The page is public, like the tournaments index: the credits and the
     * contact address are meant to be readable without an account.
     */
    public function index(): Response
    {
        return Inertia::render('About', [
            'appName' => config('app.name'),
            'laravelVersion' => app()->version(),
            'phpVersion' => PHP_VERSION,
        ]);
    }
}
