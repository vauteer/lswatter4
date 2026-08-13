<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    /**
     * Log the acting admin in as the given user, remembering the admin's
     * own id so the session can be restored later.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin->isAdmin(), 403);

        // Impersonating always swaps the ambient session identity to a
        // non-admin user, so the isAdmin() check above already prevents
        // nesting a second impersonation on top of the first.
        abort_if($user->is($admin), 404);
        abort_if($user->isAdmin(), 404);

        $request->session()->put('impersonator_id', $admin->id);
        Auth::login($user);
        $request->session()->regenerate();

        // A lingering "remember me" cookie for the admin account lets the
        // session guard silently fall back to it if the session's auth key
        // is ever read before this write is visible, reverting the swap.
        Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logged in as :name.', ['name' => $user->name])]);

        return to_route('dashboard');
    }

    /**
     * Return to the admin account that started the impersonation. Reachable
     * by the impersonated session, not just admins.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        $admin = $impersonatorId === null ? null : User::find((int) $impersonatorId);

        abort_if($admin === null, 404);

        $request->session()->forget('impersonator_id');
        Auth::login($admin);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Returned to your account.')]);

        return to_route('users.index');
    }
}
