<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poldas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // SULBAR, GORONTALO, JABAR
            $table->string('name', 100);
            $table->string('region', 50)->default('Sulawesi');
            $table->string('timezone', 50)->default('Asia/Makassar');
            $table->string('logo_url')->nullable();
            $table->string('kapolda_name')->nullable();
            $table->string('kabid_humas_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('polres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->string('code', 50); // MAMUJU, MAJENE, GORONTALO_KOTA
            $table->string('name', 100);
            $table->string('kapolres_name')->nullable();
            $table->string('kasihumas_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['polda_id', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('polda_id')->nullable()->after('id')->constrained('poldas')->nullOnDelete();
            $table->foreignId('polres_id')->nullable()->after('polda_id')->constrained('polres')->nullOnDelete();
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('role', 30)->default('polres_operator')->after('email'); // super_admin_mabes, polda_leader, polda_verifier, polres_operator
            $table->string('badge_number', 30)->nullable()->after('role'); // NRP
            $table->string('phone', 30)->nullable()->after('badge_number');
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['polda_id']);
            $table->dropForeign(['polres_id']);
            $table->dropColumn(['polda_id', 'polres_id', 'username', 'role', 'badge_number', 'phone', 'is_active']);
        });

        Schema::dropIfExists('polres');
        Schema::dropIfExists('poldas');
    }
};
