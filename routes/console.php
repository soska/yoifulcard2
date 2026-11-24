<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired batch PDFs and their files (App\Models\CardBatchPdf::prunable()).
Schedule::command('model:prune')->hourly();

// Points of cards idle longer than their program's expiry rule.
Schedule::command('rewards:expire-points')->daily();
