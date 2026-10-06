<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The batch a preissued card came from (ARM-357). Cards created one at a
     * time keep it null.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignUuid('batch_id')->nullable()->after('program_id')->index()->constrained('card_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
