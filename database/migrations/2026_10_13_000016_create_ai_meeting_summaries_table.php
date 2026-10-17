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
        Schema::create('ai_meeting_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained('meetings')->cascadeOnDelete();
            $table->text('summary_text')->nullable();
            $table->json('key_points')->nullable();
            $table->json('decisions_made')->nullable();
            $table->json('action_items')->nullable();
            $table->json('risks_identified')->nullable();
            $table->text('next_steps')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_meeting_summaries');
    }
};
