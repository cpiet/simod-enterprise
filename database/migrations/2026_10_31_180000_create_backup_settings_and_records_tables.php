<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_auto_backup_enabled')->default(true);
            $table->string('schedule_type', 50)->default('daily'); // daily, every_6_hours, every_12_hours, weekly
            $table->string('backup_time', 10)->default('02:00'); // HH:mm (WITA / WIB)
            $table->integer('retention_days')->default(14); // auto-prune backups older than X days
            $table->boolean('gzip_compression')->default(true);
            $table->timestamp('last_backup_at')->nullable();
            $table->string('last_backup_status', 50)->default('idle'); // idle, success, failed, running
            $table->string('last_backup_filename', 255)->nullable();
            $table->unsignedBigInteger('last_backup_size_bytes')->default(0);
            $table->text('last_backup_message')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255)->unique();
            $table->string('disk', 50)->default('local');
            $table->string('filepath', 500);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('formatted_size', 50)->default('0 KB');
            $table->string('backup_type', 50)->default('manual'); // manual, scheduled, uploaded
            $table->string('status', 50)->default('valid'); // valid, corrupted, restored
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('tables_count')->default(0);
            $table->decimal('duration_seconds', 6, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('backup_settings');
    }
};

