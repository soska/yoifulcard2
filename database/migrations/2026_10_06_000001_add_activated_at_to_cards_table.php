<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a preissued (inactive) card was activated with its first load
     * (ARM-356). Null for cards that were never inactive.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('activated_at');
        });
    }
};
