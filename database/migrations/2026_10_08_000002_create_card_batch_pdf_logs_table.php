<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log of batch PDFs (ARM-358): who made or downloaded one, when, of
     * which batch. Kept after the PDF itself is gone.
     */
    public function up(): void
    {
        Schema::create('card_batch_pdf_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('card_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action');
            $table->string('template');
            $table->unsignedInteger('cards');
            $table->boolean('by_admin');
            // Rows are never updated, so there is no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['card_batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_batch_pdf_logs');
    }
};
