<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Models\Superadmin;
use App\Models\User;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuperadminController extends Controller
{
    /**
     * Grant superadmin. Granting it to a superadmin changes nothing.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        Superadmin::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['granted_by' => $request->user()->id],
        );

        Flash::success(FlashMessage::SuperadminGranted, ['email' => $user->email]);

        return back(fallback: route('admin.users.index'));
    }

    /**
     * Revoke superadmin, unless it would leave no superadmin. That includes
     * revoking your own role: allowed only while another superadmin remains.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($user): void {
            // Serialize grants and revokes so two revokes cannot both pass
            // the "another one remains" check.
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE superadmins IN SHARE ROW EXCLUSIVE MODE');
            }

            $row = Superadmin::query()->where('user_id', $user->id)->first();

            if ($row === null) {
                return;
            }

            if (Superadmin::query()->count() <= 1) {
                throw ValidationException::withMessages([
                    'superadmin' => __('The last superadmin cannot be revoked. Grant the role to someone else first.'),
                ]);
            }

            $row->delete();
        });

        Flash::success(FlashMessage::SuperadminRevoked, ['email' => $user->email]);

        // Someone who revoked their own role can no longer open admin.
        if ($request->user()->is($user)) {
            return to_route('dashboard');
        }

        return back(fallback: route('admin.users.index'));
    }
}
