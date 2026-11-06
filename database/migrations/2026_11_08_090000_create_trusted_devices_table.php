<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perangkat tepercaya untuk masuk cepat dengan PIN 6 angka.
 * Perangkat hanya didaftarkan SETELAH pengguna masuk dengan nama pengguna + kata sandi yang benar dan memasang PIN.
 * Token perangkat (cookie terenkripsi) disimpan sebagai hash; PIN disimpan sebagai hash bcrypt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('pin_hash');
            $table->string('label', 160)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
