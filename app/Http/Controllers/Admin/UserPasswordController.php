<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OneTimeCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserPasswordController extends Controller
{
    /**
     * Give the user a new generated password, shown to the superadmin once.
     * This stands in for password reset until the Email release. The user's
     * other sessions and "remember me" logins stop working.
     */
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $password = OneTimeCredentials::generatePassword();

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        OneTimeCredentials::put($request, $user->email, $password);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Temporary password set for :email.', ['email' => $user->email])]);

        return back(fallback: route('admin.users.index'));
    }
}
