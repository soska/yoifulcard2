<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An IANA timezone name. Timestamps stay in UTC; this sets the
     * organization's day boundaries and displayed times. The value is checked
     * against PHP's timezone list by the Organization model.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/Mexico_City')->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
