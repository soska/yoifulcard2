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
        Schema::table('transactions', function (Blueprint $table) {
            // Entries the system writes on its own, like expiring points, have
            // no person behind them.
            $table->unsignedBigInteger('performed_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Fails while system entries exist, which have
     * no person to point at.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('performed_by')->nullable(false)->change();
        });
    }
};
