<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->nullable()->constrained('poldas')->nullOnDelete();
            $table->boolean('enable_turnstile')->default(true);
            $table->string('turnstile_site_key', 120)->nullable()->default('0x4AAAAAAAiH8s-xxxxxxxx');
            $table->string('turnstile_secret_key', 120)->nullable()->default('0x4AAAAAAAiH8s-yyyyyyyyyyyyyyyyyyyyyyyy');
            $table->integer('max_login_attempts')->default(5);
            $table->integer('lockout_minutes')->default(15);
            $table->boolean('enable_pin')->default(true);
            $table->integer('pin_expiry_days')->default(90);
            $table->integer('session_timeout_minutes')->default(30);
            $table->boolean('private_media_storage')->default(true);
            $table->text('ip_whitelist')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};

