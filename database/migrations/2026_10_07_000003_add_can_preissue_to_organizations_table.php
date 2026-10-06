<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the business can create card batches itself (ARM-357). Off by
     * default: for now we print on behalf of businesses, and superadmins can
     * always create batches.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('can_preissue')->default(false)->after('preissue_limit');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('can_preissue');
        });
    }
};
