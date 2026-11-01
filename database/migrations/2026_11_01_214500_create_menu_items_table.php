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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('route_or_url')->nullable();
            $table->json('match_patterns')->nullable();
            $table->json('exclude_patterns')->nullable();
            $table->boolean('exact_match')->default(false);
            $table->string('icon')->nullable()->default('Layers');
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('menu_location')->default('sidebar')->index(); // 'sidebar' | 'security_tabs'
            $table->integer('order_index')->default(0)->index();
            $table->string('permission_required')->nullable();
            $table->string('role_required')->nullable();
            $table->string('badge_text')->nullable();
            $table->string('badge_key')->nullable();
            $table->string('badge_color')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
