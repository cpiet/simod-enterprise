<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghapus menu "Mode Pemeliharaan" dan "Konfigurasi Mail" (halaman tanpa fungsi backend)
     * dari tabel menu_items pada instalasi yang sudah ada.
     */
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')->whereIn('route_or_url', ['/maintenance', '/settings/mail'])->delete();

        foreach (DB::table('menu_items')->where('route_or_url', '/security')->get() as $row) {
            $patterns = json_decode((string) $row->match_patterns, true) ?: [];
            $patterns = array_values(array_diff($patterns, ['/maintenance', '/settings/mail']));
            DB::table('menu_items')->where('id', $row->id)->update([
                'match_patterns'      => json_encode($patterns),
                'permission_required' => str_replace(',mail_settings', '', (string) $row->permission_required),
            ]);
        }

        DB::table('menu_items')->where('route_or_url', '/profile')->update(['description' => 'Identitas & ganti kata sandi']);
    }

    public function down(): void
    {
        // Tidak dapat dipulihkan: halaman yang dihapus tidak memiliki fungsi.
    }
};
