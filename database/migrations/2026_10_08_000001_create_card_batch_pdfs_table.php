<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A printable PDF of a card batch, asked for by one person (ARM-358). It
     * holds the token of every card it prints, so it is never kept: the file
     * and the row go away when it is downloaded or when it expires.
     */
    public function up(): void
    {
        Schema::create('card_batch_pdfs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('card_batch_id')->constrained()->cascadeOnDelete();
            $table->string('template');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            // Asked for from the admin area; only that area can download it.
            $table->boolean('by_admin')->default(false);
            // The language of the "your PDF is ready" email.
            $table->string('locale', 8);
            $table->string('path')->nullable();
            $table->unsignedInteger('cards')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['card_batch_id', 'requested_by']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_batch_pdfs');
    }
};
