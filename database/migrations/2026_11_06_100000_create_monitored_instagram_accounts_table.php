<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monitored_instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained()->cascadeOnDelete();
            $table->string('username', 30);
            $table->string('display_name')->nullable();
            $table->json('posts')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 30)->default('pending');
            $table->timestamps();
            $table->unique(['polda_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_instagram_accounts');
    }
};
