<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->boolean('rewards_enabled')->default(false);
            // 'spend' or 'visits'; null until the business picks a rule.
            $table->string('earn_mode')->nullable();
            // Points earned per 1.00 spent, when earn_mode is 'spend'.
            $table->decimal('earn_rate', 8, 2)->nullable();
            // Points expire after this many months without activity; null never.
            $table->unsignedSmallInteger('points_expiry_months')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['rewards_enabled', 'earn_mode', 'earn_rate', 'points_expiry_months']);
        });
    }
};
