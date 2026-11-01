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
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->nullable()->constrained('poldas')->cascadeOnDelete();
            $table->text('gemini_api_key')->nullable();
            $table->string('gemini_model', 100)->default('gemini-2.5-pro');
            $table->boolean('is_auto_analyze')->default(false);
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->timestamp('last_analyzed_at')->nullable();
            $table->string('last_status', 50)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};

