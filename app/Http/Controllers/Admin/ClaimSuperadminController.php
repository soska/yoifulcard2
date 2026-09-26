<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Models\Superadmin;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimSuperadminController extends Controller
{
    /**
     * Let the first signed-in user become superadmin, only while the
     * superadmins table is empty.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $claimed = DB::transaction(function () use ($user): bool {
            // Serialize concurrent claims so two users cannot both win.
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE superadmins IN SHARE ROW EXCLUSIVE MODE');
            }

            if (Superadmin::query()->exists()) {
                return false;
            }

            Superadmin::create([
                'user_id' => $user->id,
                'granted_by' => $user->id,
            ]);

            return true;
        });

        abort_unless($claimed, 403);

        Flash::success(FlashMessage::SuperadminClaimed);

        return to_route('admin.index');
    }
}
