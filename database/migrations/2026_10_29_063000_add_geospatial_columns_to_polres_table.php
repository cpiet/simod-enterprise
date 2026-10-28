<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polres', function (Blueprint $table) {
            $table->string('short_name', 50)->nullable()->after('name');
            $table->string('tag_desc', 150)->nullable()->after('short_name');
            $table->decimal('latitude', 10, 7)->nullable()->after('tag_desc');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->integer('map_x')->default(50)->after('longitude');
            $table->integer('map_y')->default(50)->after('map_x');
        });
    }

    public function down(): void
    {
        Schema::table('polres', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'tag_desc', 'latitude', 'longitude', 'map_x', 'map_y']);
        });
    }
};
