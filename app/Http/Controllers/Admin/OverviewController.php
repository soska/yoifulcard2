<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class OverviewController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('admin.organizations.index');
    }
}
