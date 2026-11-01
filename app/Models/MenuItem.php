<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class MenuItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'route_or_url',
        'match_patterns',
        'exclude_patterns',
        'exact_match',
        'icon',
        'parent_id',
        'menu_location',
        'order_index',
        'permission_required',
        'role_required',
        'badge_text',
        'badge_key',
        'badge_color',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'match_patterns'   => 'array',
        'exclude_patterns' => 'array',
        'exact_match'      => 'boolean',
        'order_index'      => 'integer',
        'is_active'        => 'boolean',
        'is_system'        => 'boolean',
    ];

    /**
     * Parent menu item (e.g. Pillar group or parent dropdown).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Active child menu items ordered by order_index.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order_index')->orderBy('id');
    }

    /**
     * Check if a menu item is accessible by the given user role and permissions map.
     */
    public function isAccessibleBy(string $role, array $permissions): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Profile is always accessible to authenticated personnel
        if ($this->route_or_url === '/profile') {
            return true;
        }

        // Check role_required if specified
        if (!empty($this->role_required)) {
            $allowedRoles = array_filter(array_map('trim', explode(',', $this->role_required)));
            if (!empty($allowedRoles) && !in_array($role, $allowedRoles, true)) {
                return false;
            }
        }

        // Check permission_required (supports single or comma-separated OR permissions)
        if (!empty($this->permission_required)) {
            $requiredPerms = array_filter(array_map('trim', explode(',', $this->permission_required)));
            if (!empty($requiredPerms)) {
                $hasAny = false;
                foreach ($requiredPerms as $perm) {
                    if (($permissions[$perm] ?? true) !== false) {
                        $hasAny = true;
                        break;
                    }
                }
                if (!$hasAny) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Build dynamic navigation structure (sidebar pillars + security tabs) filtered by RBAC.
     */
    public static function getNavigationForUser(string $role, array $permissions): array
    {
        if (!Schema::hasTable('menu_items')) {
            return [
                'sidebarGroups' => [],
                'securityTabs'  => [],
            ];
        }

        self::ensureDefaultMenusSeeded();

        // 1. Sidebar Pillars & Child Items
        $pillars = self::with(['children.children'])
            ->where('menu_location', 'sidebar')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        $sidebarGroups = [];
        foreach ($pillars as $pillar) {
            if (!$pillar->isAccessibleBy($role, $permissions)) {
                continue;
            }

            $visibleItems = [];
            foreach ($pillar->children as $child) {
                if (!$child->isAccessibleBy($role, $permissions)) {
                    continue;
                }

                $subItems = [];
                foreach ($child->children as $sub) {
                    if ($sub->isAccessibleBy($role, $permissions)) {
                        $subItems[] = self::formatMenuItem($sub);
                    }
                }

                $formatted = self::formatMenuItem($child);
                $formatted['children'] = $subItems;
                $visibleItems[] = $formatted;
            }

            if (!empty($visibleItems)) {
                $sidebarGroups[] = [
                    'id'          => $pillar->id,
                    'name'        => $pillar->title,
                    'description' => $pillar->description,
                    'icon'        => $pillar->icon,
                    'order_index' => $pillar->order_index,
                    'items'       => $visibleItems,
                ];
            }
        }

        // 2. Security & Maintenance Sub-Navigation Tabs
        $tabs = self::where('menu_location', 'security_tabs')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        $securityTabs = [];
        foreach ($tabs as $tab) {
            if ($tab->isAccessibleBy($role, $permissions)) {
                $securityTabs[] = self::formatMenuItem($tab);
            }
        }

        return [
            'sidebarGroups' => $sidebarGroups,
            'securityTabs'  => $securityTabs,
        ];
    }

    /**
     * Format a MenuItem model for frontend consumption.
     */
    public static function formatMenuItem(self $item): array
    {
        $match = !empty($item->match_patterns)
            ? $item->match_patterns
            : ($item->route_or_url ? [$item->route_or_url] : []);

        return [
            'id'          => $item->id,
            'name'        => $item->title,
            'title'       => $item->title,
            'desc'        => $item->description,
            'href'        => $item->route_or_url ?: '#',
            'icon'        => $item->icon ?: 'Layers',
            'match'       => $match,
            'exclude'     => $item->exclude_patterns ?: [],
            'exact'       => (bool) $item->exact_match,
            'badge'       => $item->badge_text,
            'badgeKey'    => $item->badge_key,
            'badgeColor'  => $item->badge_color,
            'permission'  => $item->permission_required,
            'role'        => $item->role_required,
            'order_index' => $item->order_index,
            'is_active'   => (bool) $item->is_active,
        ];
    }

    /**
     * Ensure default enterprise menus are seeded if table is empty.
     */
    public static function ensureDefaultMenusSeeded(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        if (self::count() > 0) {
            return;
        }

        self::seedDefaults();
    }

    /**
     * Reset and re-seed default enterprise menu structure.
     */
    public static function resetToDefaults(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        self::query()->delete();
        self::seedDefaults();
    }

    /**
     * Seed the 4 Pillars, 13 Sidebar Items, and 10 Security/Maintenance Sub-Tabs.
     */
    public static function seedDefaults(): void
    {
        // =====================================================================
        // PILAR 1: KOMANDO & INTELIJEN
        // =====================================================================
        $pilar1 = self::create([
            'title'         => 'KOMANDO & INTELIJEN',
            'description'   => 'Pusat komando eksekutif dan intelijen media siber',
            'icon'          => 'Shield',
            'menu_location' => 'sidebar',
            'order_index'   => 1,
            'is_active'     => true,
            'is_system'     => true,
        ]);

        self::create([
            'parent_id'           => $pilar1->id,
            'title'               => 'Command Center',
            'description'         => 'Ringkasan Eksekutif & EWS',
            'route_or_url'        => '/',
            'match_patterns'      => ['/', '/dashboard', '/command-center'],
            'icon'                => 'LayoutDashboard',
            'menu_location'       => 'sidebar',
            'order_index'         => 1,
            'permission_required' => 'dashboard_view',
            'badge_text'          => 'LIVE',
            'badge_color'         => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar1->id,
            'title'               => 'Radar Media Online',
            'description'         => 'Agregasi 29 RSS & Berita Satwil',
            'route_or_url'        => '/media-intelligence',
            'match_patterns'      => ['/media-intelligence', '/monitoring-media-online'],
            'icon'                => 'Radio',
            'menu_location'       => 'sidebar',
            'order_index'         => 2,
            'permission_required' => 'media_intelligence_view',
            'badge_text'          => 'ONLINE',
            'badge_color'         => 'bg-amber-400/20 text-amber-300 border-amber-400/30',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar1->id,
            'title'               => 'Radar Media Sosial',
            'description'         => 'Pantauan 𝕏, TikTok, IG, YT & KOL',
            'route_or_url'        => '/social-intelligence',
            'match_patterns'      => ['/social-intelligence'],
            'icon'                => 'Share2',
            'menu_location'       => 'sidebar',
            'order_index'         => 3,
            'permission_required' => 'social_intelligence_view',
            'badge_text'          => 'RADAR',
            'badge_color'         => 'bg-sky-500/20 text-sky-400 border border-sky-500/30',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        // =====================================================================
        // PILAR 2: MANAJEMEN ISU & TUGAS
        // =====================================================================
        $pilar2 = self::create([
            'title'         => 'MANAJEMEN ISU & TUGAS',
            'description'   => 'Siklus pelaporan isu kewilayahan, verifikasi fakta, dan disposisi',
            'icon'          => 'Flame',
            'menu_location' => 'sidebar',
            'order_index'   => 2,
            'is_active'     => true,
            'is_system'     => true,
        ]);

        self::create([
            'parent_id'           => $pilar2->id,
            'title'               => 'Laporan Polres & Isu',
            'description'         => 'Pemantauan & Eskalasi Berita',
            'route_or_url'        => '/issues',
            'match_patterns'      => ['/issues'],
            'exclude_patterns'    => ['/issues/create'],
            'icon'                => 'Flame',
            'menu_location'       => 'sidebar',
            'order_index'         => 1,
            'permission_required' => 'issues_view',
            'badge_key'           => 'total_issues',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar2->id,
            'title'               => 'Input Laporan Baru',
            'description'         => 'Formulir Laporan Lapangan & Foto',
            'route_or_url'        => '/issues/create',
            'match_patterns'      => ['/issues/create'],
            'exact_match'         => true,
            'icon'                => 'PlusCircle',
            'menu_location'       => 'sidebar',
            'order_index'         => 2,
            'permission_required' => 'issues_create',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar2->id,
            'title'               => 'Antrean Verifikasi',
            'description'         => 'Validasi Fakta & Berita Satker',
            'route_or_url'        => '/verification-queue',
            'match_patterns'      => ['/verification-queue'],
            'icon'                => 'CheckCircle2',
            'menu_location'       => 'sidebar',
            'order_index'         => 3,
            'permission_required' => 'issues_verify',
            'badge_key'           => 'pending_verify',
            'badge_color'         => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar2->id,
            'title'               => 'Disposisi Tugas',
            'description'         => 'Penugasan Anggota & Laporan',
            'route_or_url'        => '/tasks',
            'match_patterns'      => ['/tasks'],
            'icon'                => 'CheckSquare',
            'menu_location'       => 'sidebar',
            'order_index'         => 4,
            'permission_required' => 'tasks_disposition',
            'badge_key'           => 'active_tasks',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        // =====================================================================
        // PILAR 3: PUBLIKASI & DOKUMENTASI
        // =====================================================================
        $pilar3 = self::create([
            'title'         => 'PUBLIKASI & DOKUMENTASI',
            'description'   => 'Orkestrasi rilis pers multi-kanal, galeri bukti, dan laporan Anev',
            'icon'          => 'Share2',
            'menu_location' => 'sidebar',
            'order_index'   => 3,
            'is_active'     => true,
            'is_system'     => true,
        ]);

        self::create([
            'parent_id'           => $pilar3->id,
            'title'               => 'Publikasi Multi-Kanal',
            'description'         => 'Tribratanews, IG, TikTok, FB, X',
            'route_or_url'        => '/publications',
            'match_patterns'      => ['/publications'],
            'icon'                => 'Share2',
            'menu_location'       => 'sidebar',
            'order_index'         => 1,
            'permission_required' => 'publications_manage',
            'badge_key'           => 'total_publications',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar3->id,
            'title'               => 'Galeri Media Aman',
            'description'         => 'Dokumentasi & Bukti Terenkripsi',
            'route_or_url'        => '/gallery',
            'match_patterns'      => ['/gallery'],
            'icon'                => 'Image',
            'menu_location'       => 'sidebar',
            'order_index'         => 2,
            'permission_required' => 'gallery_access',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar3->id,
            'title'               => 'Laporan & Anev',
            'description'         => 'Rekapitulasi Kinerja & Ekspor',
            'route_or_url'        => '/reports',
            'match_patterns'      => ['/reports'],
            'icon'                => 'FileBarChart2',
            'menu_location'       => 'sidebar',
            'order_index'         => 3,
            'permission_required' => 'reports_export',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        // =====================================================================
        // PILAR 4: ADMINISTRASI & SISTEM
        // =====================================================================
        $pilar4 = self::create([
            'title'         => 'ADMINISTRASI & SISTEM',
            'description'   => 'Manajemen personel, satwil, keamanan siber, dan menu dinamis',
            'icon'          => 'Settings',
            'menu_location' => 'sidebar',
            'order_index'   => 4,
            'is_active'     => true,
            'is_system'     => true,
        ]);

        self::create([
            'parent_id'           => $pilar4->id,
            'title'               => 'Personalia & Petugas',
            'description'         => 'Manajemen Akun Anggota',
            'route_or_url'        => '/personalia',
            'match_patterns'      => ['/personalia'],
            'icon'                => 'Users',
            'menu_location'       => 'sidebar',
            'order_index'         => 1,
            'permission_required' => 'personalia_manage',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar4->id,
            'title'               => 'Pengaturan Satwil & Mabes',
            'description'         => 'Konfigurasi Divhumas, Polda & Polres',
            'route_or_url'        => '/settings/polda',
            'match_patterns'      => ['/settings/polda'],
            'icon'                => 'Building2',
            'menu_location'       => 'sidebar',
            'order_index'         => 2,
            'permission_required' => 'polda_settings',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar4->id,
            'title'               => 'Keamanan & Pemeliharaan',
            'description'         => 'Keamanan, RBAC, Menu, Audit, Backup, Sinkron RSS, AI',
            'route_or_url'        => '/security',
            'match_patterns'      => [
                '/security',
                '/roles',
                '/settings/menus',
                '/audit-logs',
                '/database/backup',
                '/maintenance',
                '/settings/mail',
                '/maintenance/news-cleanup',
                '/maintenance/rss-sync',
                '/settings/ai',
            ],
            'icon'                => 'ShieldAlert',
            'menu_location'       => 'sidebar',
            'order_index'         => 3,
            'permission_required' => 'security_center,roles_matrix_manage,database_backup,maintenance_ops,mail_settings',
            'is_active'           => true,
            'is_system'           => true,
        ]);

        self::create([
            'parent_id'           => $pilar4->id,
            'title'               => 'Profil Saya',
            'description'         => 'Kredensial & PIN Cepat',
            'route_or_url'        => '/profile',
            'match_patterns'      => ['/profile'],
            'icon'                => 'UserCheck',
            'menu_location'       => 'sidebar',
            'order_index'         => 4,
            'permission_required' => null,
            'is_active'           => true,
            'is_system'           => true,
        ]);

        // =====================================================================
        // SUB-MODUL TABS: KEAMANAN & PEMELIHARAAN (10 TABS)
        // =====================================================================
        $securityTabs = [
            ['title' => 'Keamanan', 'route' => '/security', 'icon' => 'ShieldAlert', 'perm' => 'security_center', 'order' => 1],
            ['title' => 'Hak Akses', 'route' => '/roles', 'icon' => 'KeyRound', 'perm' => 'roles_matrix_manage', 'order' => 2],
            ['title' => 'Manajemen Menu', 'route' => '/settings/menus', 'icon' => 'SlidersHorizontal', 'perm' => 'roles_matrix_manage', 'order' => 3],
            ['title' => 'Audit Log', 'route' => '/audit-logs', 'icon' => 'History', 'perm' => 'security_center', 'order' => 4],
            ['title' => 'Backup Data', 'route' => '/database/backup', 'icon' => 'Database', 'perm' => 'database_backup', 'order' => 5],
            ['title' => 'Pembersihan Berita', 'route' => '/maintenance/news-cleanup', 'icon' => 'Eraser', 'perm' => 'maintenance_ops', 'order' => 6],
            ['title' => 'Otomasi Sinkron RSS', 'route' => '/maintenance/rss-sync', 'icon' => 'Radio', 'perm' => 'maintenance_ops', 'order' => 7],
            ['title' => 'Mode Pemeliharaan', 'route' => '/maintenance', 'icon' => 'Wrench', 'perm' => 'maintenance_ops', 'order' => 8],
            ['title' => 'Konfigurasi Mail', 'route' => '/settings/mail', 'icon' => 'Mail', 'perm' => 'mail_settings', 'order' => 9],
            ['title' => 'Mesin Intelijen AI', 'route' => '/settings/ai', 'icon' => 'Bot', 'perm' => 'security_center', 'order' => 10],
        ];

        foreach ($securityTabs as $tab) {
            self::create([
                'title'               => $tab['title'],
                'description'         => 'Sub-modul Keamanan & Pemeliharaan Sistem',
                'route_or_url'        => $tab['route'],
                'match_patterns'      => [$tab['route']],
                'icon'                => $tab['icon'],
                'menu_location'       => 'security_tabs',
                'order_index'         => $tab['order'],
                'permission_required' => $tab['perm'],
                'is_active'           => true,
                'is_system'           => true,
            ]);
        }
    }
}
