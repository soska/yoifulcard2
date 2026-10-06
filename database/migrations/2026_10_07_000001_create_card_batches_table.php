<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A batch of preissued (inactive) cards made at once for printing
     * (ARM-357). `template` stays null until PDF printing lands.
     */
    public function up(): void
    {
        Schema::create('card_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('count');
            $table->string('template')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('issued_by_admin')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_batches');
    }
};
