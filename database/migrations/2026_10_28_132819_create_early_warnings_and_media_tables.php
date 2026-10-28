<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('early_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('polres_id')->nullable()->constrained('polres')->nullOnDelete();
            $table->string('title');
            $table->enum('level', ['green', 'yellow', 'red'])->default('yellow');
            $table->string('category', 100); // BBM, Kecelakaan, Unjuk Rasa, Sengketa Agraria, dll
            $table->text('facts'); // Fakta & Kondisi Riil
            $table->text('reputation_risk'); // Risiko Reputasi Institusi
            $table->text('protocol_0_2h'); // Respons 0-2 Jam
            $table->text('protocol_2_6h'); // Respons 2-6 Jam
            $table->text('protocol_6_24h'); // Respons 6-24 Jam
            $table->string('pic_units'); // PIC Satker terkait
            $table->enum('status', ['active', 'monitored', 'resolved'])->default('active');
            $table->timestamps();
        });

        Schema::create('media_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 255);
            $table->string('category', 50)->default('Regional');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('media_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('feed_id')->nullable()->constrained('media_feeds')->nullOnDelete();
            $table->string('title');
            $table->string('source_name', 100);
            $table->string('category', 50)->default('Nasional');
            $table->string('url', 500);
            $table->string('image_url', 500)->nullable();
            $table->text('summary')->nullable();
            $table->dateTime('pub_date')->nullable();
            $table->enum('sentiment', ['positif', 'netral', 'negatif'])->default('netral');
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_articles');
        Schema::dropIfExists('media_feeds');
        Schema::dropIfExists('early_warnings');
    }
};
