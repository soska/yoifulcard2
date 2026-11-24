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
            // A redemption's reward. The name is kept as it was when the
            // reward was redeemed (the cost is the entry's amount), so editing
            // or removing the reward later does not change the history.
            $table->foreignUuid('reward_id')->nullable()->after('performed_by')->constrained('rewards')->nullOnDelete();
            $table->string('reward_name', 120)->nullable()->after('reward_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reward_id');
            $table->dropColumn('reward_name');
        });
    }
};
