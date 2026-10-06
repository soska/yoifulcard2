<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The most unactivated (inactive) cards a business can hold at once
     * (ARM-356). Set by superadmins next to card_limit; null is unlimited.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('preissue_limit')->nullable()->after('card_limit');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('preissue_limit');
        });
    }
};
