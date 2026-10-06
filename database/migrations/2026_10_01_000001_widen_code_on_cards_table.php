<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Card codes grow from YGFT-XXXX to YGFT-XXXXXX (ARM-355).
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('code', 16)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('code', 9)->change();
        });
    }
};
