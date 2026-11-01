<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'role_key',
        'role_name',
        'description',
        'permissions',
        'updated_by',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Definisi lengkap modul sistem berdasarkan 4 Pilar Hierarki SIMOD Enterprise.
     */
    public static function defaultModules(): array
    {
        return [
            // PILAR 1: KOMANDO & INTELIJEN
            [
                'pillar'      => '1. Komando & Intelijen',
                'key'         => 'dashboard_view',
                'name'        => 'Command Center & Situasi Wilayah',
                'description' => 'Akses ringkasan eksekutif, peta interaktif situasi kamtibmas, dan Early Warning System (EWS).',
            ],
            [
                'pillar'      => '1. Komando & Intelijen',
                'key'         => 'media_intelligence_view',
                'name'        => 'Radar Media Online (29 RSS)',
                'description' => 'Akses pemantauan agregasi berita siber, kliping online, dan portal berita nasional & daerah.',
            ],
            [
                'pillar'      => '1. Komando & Intelijen',
                'key'         => 'media_intelligence_ai',
                'name'        => 'Analisis AI & Naskah WA Pimpinan',
                'description' => 'Eksekusi prompt AI, input hasil ringkasan analis, dan generate naskah WhatsApp resmi pimpinan.',
            ],
            [
                'pillar'      => '1. Komando & Intelijen',
                'key'         => 'media_intelligence_manage',
                'name'        => 'Manajemen Sumber RSS & Uji URL',
                'description' => 'Tambah, edit, uji validitas XML, dan aktifkan/nonaktifkan portal media RSS.',
            ],
            [
                'pillar'      => '1. Komando & Intelijen',
                'key'         => 'social_intelligence_view',
                'name'        => 'Radar Media Sosial (𝕏, TikTok, IG, YT)',
                'description' => 'Pantauan sentimen medsos, trending tagar, virality score, dan daftar akun tokoh kritis/KOL.',
            ],
            [
                'pillar'      => '1. Komando & Intelijen',
                'key'         => 'social_intelligence_sync',
                'name'        => 'Sinkronisasi Apify & Pengaturan Medsos',
                'description' => 'Trigger scraper data Apify, konfigurasi token API, dan atur akun yang dipantau.',
            ],

            // PILAR 2: MANAJEMEN ISU & TUGAS
            [
                'pillar'      => '2. Manajemen Isu & Tugas',
                'key'         => 'issues_view',
                'name'        => 'Pemantauan Laporan Polres & Isu',
                'description' => 'Melihat daftar isu kamtibmas, filter sentimen, dan riwayat eskalasi isu publik.',
            ],
            [
                'pillar'      => '2. Manajemen Isu & Tugas',
                'key'         => 'issues_create',
                'name'        => 'Input Laporan Lapangan & Bukti',
                'description' => 'Membuat laporan isu baru dari temuan siber atau lapangan dengan lampiran bukti foto/video.',
            ],
            [
                'pillar'      => '2. Manajemen Isu & Tugas',
                'key'         => 'issues_verify',
                'name'        => 'Verifikasi Fakta & Klarifikasi Hoaks',
                'description' => 'Melakukan investigasi fakta riil, konfirmasi satu pintu satwil, dan rilis klarifikasi bantahan hoaks.',
            ],
            [
                'pillar'      => '2. Manajemen Isu & Tugas',
                'key'         => 'tasks_disposition',
                'name'        => 'Disposisi Surat Perintah & Tugas',
                'description' => 'Menerbitkan surat perintah tugas personel dan menetapkan tenggat waktu eksekusi lapangan.',
            ],
            [
                'pillar'      => '2. Manajemen Isu & Tugas',
                'key'         => 'tasks_execute',
                'name'        => 'Laporan Pelaksanaan Tugas Personel',
                'description' => 'Memperbarui progres penugasan dan mengunggah dokumen/foto bukti penyelesaian tugas.',
            ],

            // PILAR 3: PUBLIKASI & DOKUMENTASI
            [
                'pillar'      => '3. Publikasi & Dokumentasi',
                'key'         => 'publications_manage',
                'name'        => 'Publikasi Rilis Multi-Kanal Medsos',
                'description' => 'Menyusun dan menjadwalkan rilis Tribrata News, Instagram, TikTok, Facebook, dan X.',
            ],
            [
                'pillar'      => '3. Publikasi & Dokumentasi',
                'key'         => 'gallery_access',
                'name'        => 'Galeri Media & Arsip Bukti Terenkripsi',
                'description' => 'Akses brankas media aman, upload foto/video dinas, dan pengarsipan dokumentasi.',
            ],
            [
                'pillar'      => '3. Publikasi & Dokumentasi',
                'key'         => 'reports_export',
                'name'        => 'Laporan Kinerja Anev & Ekspor CSV/PDF',
                'description' => 'Melihat statistik anev kehumasan dan mengunduh rekapan kinerja resmi.',
            ],

            // PILAR 4: ADMINISTRASI & SISTEM
            [
                'pillar'      => '4. Administrasi & Sistem',
                'key'         => 'personalia_manage',
                'name'        => 'Manajemen Akun Personalia & Satker',
                'description' => 'Pendaftaran anggota baru, perubahan password dinas, dan pengaturan peran (role).',
            ],
            [
                'pillar'      => '4. Administrasi & Sistem',
                'key'         => 'polda_settings',
                'name'        => 'Konfigurasi Master Satwil (Polda/Polres)',
                'description' => 'Mengatur profil Polda, pimpinan satuan, zona waktu, dan master data Polres jajaran.',
            ],
            [
                'pillar'      => '4. Administrasi & Sistem',
                'key'         => 'security_center',
                'name'        => 'Keamanan Siber, PIN & Cloudflare Turnstile',
                'description' => 'Mengatur bot protection, whitelist IP, masa berlaku PIN, dan audit log keamanan.',
            ],
            [
                'pillar'      => '4. Administrasi & Sistem',
                'key'         => 'database_backup',
                'name'        => 'Backup & Restore Basis Data',
                'description' => 'Membuat snapshot cadangan basis data terkompresi, unduh sql.gz, dan restore sistem.',
            ],
            [
                'pillar'      => '4. Administrasi & Sistem',
                'key'         => 'maintenance_ops',
                'name'        => 'Pemeliharaan, News Cleanup & RSS Sync',
                'description' => 'Menjalankan pruning berita lama, sinkronisasi RSS paksa, dan mengaktifkan mode maintenance.',
            ],
            [
                'pillar'      => '4. Administrasi & Sistem',
                'key'         => 'mail_settings',
                'name'        => 'Konfigurasi Mail Server (SMTP)',
                'description' => 'Mengatur kredensial SMTP, port, enkripsi, dan uji kirim email laporan notifikasi dinas.',
            ],
            [
                'pillar'      => '4. Administrasi & Sistem',
                'key'         => 'roles_matrix_manage',
                'name'        => 'Otoritas & Matriks Hak Akses (RBAC)',
                'description' => 'Mengatur izin operasional fitur dan batasan wewenang untuk setiap peran personel.',
            ],
        ];
    }

    /**
     * Matriks Hak Akses Default Berstandar Divisi Humas Polri & Polda Jajaran.
     */
    public static function defaultRoles(): array
    {
        return [
            [
                'role_key'    => 'kadiv_humas',
                'role_name'   => 'Kadivhumas Polri',
                'badge'       => 'Mabes ⭐⭐',
                'description' => 'Pucuk pimpinan tertinggi kehumasan Polri. Memiliki otoritas penuh atas seluruh orkestrasi 34 Polda.',
                'all_granted' => true,
            ],
            [
                'role_key'    => 'super_admin_mabes',
                'role_name'   => 'Super Administrator Mabes',
                'badge'       => 'Superadmin',
                'description' => 'Pengelola sistem tingkat nasional, konfigurasi infrastruktur server, keamanan siber, dan audit.',
                'all_granted' => true,
            ],
            [
                'role_key'    => 'polda_leader',
                'role_name'   => 'Pimpinan Polda (Kabid Humas)',
                'badge'       => 'Polda',
                'description' => 'Pimpinan kehumasan tingkat Polda. Memiliki wewenang komando penuh di wilayah hukum daerah.',
                'granted_keys'=> [
                    'dashboard_view', 'media_intelligence_view', 'media_intelligence_ai', 'media_intelligence_manage',
                    'social_intelligence_view', 'social_intelligence_sync', 'issues_view', 'issues_create',
                    'issues_verify', 'tasks_disposition', 'tasks_execute', 'publications_manage', 'gallery_access',
                    'reports_export', 'personalia_manage', 'polda_settings', 'news_cleanup', 'rss_sync_manage',
                ],
            ],
            [
                'role_key'    => 'verifikator',
                'role_name'   => 'Verifikator (Kasubbid Penmas)',
                'badge'       => 'Verifikator',
                'description' => 'Penanggung jawab validasi fakta, penyusunan counter narasi, konfirmasi satwil, dan penerbitan rilis pers.',
                'granted_keys'=> [
                    'dashboard_view', 'media_intelligence_view', 'media_intelligence_ai', 'social_intelligence_view',
                    'issues_view', 'issues_create', 'issues_verify', 'tasks_disposition', 'tasks_execute',
                    'publications_manage', 'gallery_access', 'reports_export',
                ],
            ],
            [
                'role_key'    => 'analis',
                'role_name'   => 'Analis Intelijen Media & Siber',
                'badge'       => 'Analis',
                'description' => 'Spesialis pemantauan media siber, analisis sentimen AI, deteksi dini isu viral, dan formulasi EWS.',
                'granted_keys'=> [
                    'dashboard_view', 'media_intelligence_view', 'media_intelligence_ai', 'social_intelligence_view',
                    'issues_view', 'issues_create', 'gallery_access', 'reports_export',
                ],
            ],
            [
                'role_key'    => 'operator',
                'role_name'   => 'Operator Satwil (Polres / Sie Humas)',
                'badge'       => 'Operator',
                'description' => 'Personel pelaksana di Polres/Polsek jajaran. Bertugas menginput laporan lapangan dan mengeksekusi penugasan.',
                'granted_keys'=> [
                    'dashboard_view', 'media_intelligence_view', 'social_intelligence_view', 'issues_view',
                    'issues_create', 'tasks_execute', 'gallery_access',
                ],
            ],
        ];
    }

    /**
     * Ambil matriks hak akses untuk Polda tertentu (atau nasional).
     * Jika belum ada di basis data, otomatis diinisialisasi dari standar Polri.
     */
    public static function getMatrixForPolda(?int $poldaId = null): array
    {
        $existing = static::where('polda_id', $poldaId)->get()->keyBy('role_key');
        $modules = static::defaultModules();
        $allModuleKeys = array_column($modules, 'key');

        $result = [];

        foreach (static::defaultRoles() as $def) {
            $roleKey = $def['role_key'];

            if ($existing->has($roleKey)) {
                $record = $existing->get($roleKey);
                $perms = $record->permissions ?? [];
            } else {
                // Buat permissions default
                $perms = [];
                if (!empty($def['all_granted'])) {
                    foreach ($allModuleKeys as $k) {
                        $perms[$k] = true;
                    }
                } else {
                    $granted = $def['granted_keys'] ?? [];
                    foreach ($allModuleKeys as $k) {
                        $perms[$k] = in_array($k, $granted, true);
                    }
                }

                $record = static::create([
                    'polda_id'    => $poldaId,
                    'role_key'    => $roleKey,
                    'role_name'   => $def['role_name'],
                    'description' => $def['description'],
                    'permissions' => $perms,
                ]);
            }

            // Pastikan seluruh modul terdaftar dalam array permissions
            $safePerms = $record->permissions ?: [];
            foreach ($allModuleKeys as $k) {
                if (!array_key_exists($k, $safePerms)) {
                    $safePerms[$k] = !empty($def['all_granted']);
                }
            }

            $result[] = [
                'id'          => $record->id,
                'role_key'    => $roleKey,
                'role_name'   => $record->role_name,
                'badge'       => $def['badge'] ?? 'Peran',
                'description' => $record->description,
                'permissions' => $safePerms,
                'updated_at'  => $record->updated_at ? $record->updated_at->format('d M Y, H:i') : null,
            ];
        }

        return $result;
    }

    /**
     * Pengecekan cepat apakah suatu role memiliki wewenang untuk modul tertentu.
     */
    public static function isGranted(string $roleKey, string $moduleKey, ?int $poldaId = null): bool
    {
        // 1. Cari pengaturan spesifik di basis data
        $rec = static::where('role_key', $roleKey)
            ->where('polda_id', $poldaId)
            ->first();

        // Fallback jika poldaId spesifik belum ada, cek pengaturan nasional / Mabes
        if (!$rec && $poldaId !== null) {
            $divhumasId = Polda::where('code', 'DIVHUMAS')->value('id');
            $rec = static::where('role_key', $roleKey)
                ->where(function ($q) use ($divhumasId) {
                    $q->whereNull('polda_id')->orWhere('polda_id', $divhumasId);
                })
                ->first();
        }

        if ($rec && isset($rec->permissions[$moduleKey])) {
            return (bool) $rec->permissions[$moduleKey];
        }

        // 2. Fallback ke standar peran bawaan jika belum ada di database
        foreach (static::defaultRoles() as $def) {
            if ($def['role_key'] === $roleKey) {
                if (!empty($def['all_granted'])) return true;
                return in_array($moduleKey, $def['granted_keys'] ?? [], true);
            }
        }

        return false;
    }

    /**
     * Ambil seluruh permissions (array associative key => bool) untuk suatu role.
     */
    public static function getPermissionsForRole(string $roleKey, ?int $poldaId = null): array
    {
        $rec = static::where('role_key', $roleKey)
            ->where('polda_id', $poldaId)
            ->first();

        if (!$rec && $poldaId !== null) {
            $divhumasId = Polda::where('code', 'DIVHUMAS')->value('id');
            $rec = static::where('role_key', $roleKey)
                ->where(function ($q) use ($divhumasId) {
                    $q->whereNull('polda_id')->orWhere('polda_id', $divhumasId);
                })
                ->first();
        }

        if ($rec && is_array($rec->permissions)) {
            return $rec->permissions;
        }

        $allModules = array_column(static::defaultModules(), 'key');
        $perms = [];
        foreach (static::defaultRoles() as $def) {
            if ($def['role_key'] === $roleKey) {
                $granted = !empty($def['all_granted']) ? $allModules : ($def['granted_keys'] ?? []);
                foreach ($allModules as $k) {
                    $perms[$k] = in_array($k, $granted, true);
                }
                return $perms;
            }
        }

        foreach ($allModules as $k) {
            $perms[$k] = true;
        }
        return $perms;
    }
}

