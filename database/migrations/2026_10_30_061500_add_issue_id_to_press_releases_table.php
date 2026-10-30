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
        Schema::table('press_releases', function (Blueprint $table) {
            if (!Schema::hasColumn('press_releases', 'issue_id')) {
                $table->foreignId('issue_id')->nullable()->after('polres_id')->constrained('issues')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('press_releases', function (Blueprint $table) {
            if (Schema::hasColumn('press_releases', 'issue_id')) {
                $table->dropForeign(['issue_id']);
                $table->dropColumn('issue_id');
            }
        });
    }
};

