<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Polda;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuManagementController extends Controller
{
    /**
     * Tampilkan panel manajemen navigasi & menu dinamis untuk Administrator.
     */
    public function index(Request $request): Response
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->orderBy('name')->get();

        MenuItem::ensureDefaultMenusSeeded();

        // Sidebar Pillars with nested children
        $sidebarPillars = MenuItem::with(['children.children'])
            ->where('menu_location', 'sidebar')
            ->whereNull('parent_id')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        // Security & Maintenance Sub-Tabs
        $securityTabs = MenuItem::where('menu_location', 'security_tabs')
            ->whereNull('parent_id')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        // Available RBAC Modules & Roles
        $availableModules = RolePermission::defaultModules();
        $availableRoles = [
            ['key' => 'kadiv_humas', 'label' => 'Kadiv Humas Polri'],
            ['key' => 'super_admin_mabes', 'label' => 'Super Admin Mabes'],
            ['key' => 'polda_leader', 'label' => 'Kabid Humas Polda'],
            ['key' => 'verifikator', 'label' => 'Kasubbid / Verifikator'],
            ['key' => 'analis', 'label' => 'Analis Intelijen Media'],
            ['key' => 'operator', 'label' => 'Operator Satwil / Polres'],
        ];

        $availableIcons = [
            'LayoutDashboard',
            'Radio',
            'Share2',
            'Flame',
            'PlusCircle',
            'CheckCircle2',
            'CheckSquare',
            'Image',
            'FileBarChart2',
            'Users',
            'Building2',
            'ShieldAlert',
            'Shield',
            'UserCheck',
            'KeyRound',
            'SlidersHorizontal',
            'History',
            'Database',
            'Eraser',
            'Wrench',
            'Mail',
            'Bot',
            'Layers',
            'Globe',
            'Lock',
            'Bell',
            'Menu',
        ];

        $stats = [
            'total_items'    => MenuItem::count(),
            'active_items'   => MenuItem::where('is_active', true)->count(),
            'inactive_items' => MenuItem::where('is_active', false)->count(),
            'pillars_count'  => $sidebarPillars->count(),
            'tabs_count'     => $securityTabs->count(),
        ];

        return Inertia::render('Settings/Menus', [
            'allPoldas'        => $allPoldas,
            'selectedPolda'    => $selectedPolda,
            'selectedCode'     => $selectedCode,
            'sidebarPillars'   => $sidebarPillars,
            'securityTabs'     => $securityTabs,
            'availableModules' => $availableModules,
            'availableRoles'   => $availableRoles,
            'availableIcons'   => $availableIcons,
            'stats'            => $stats,
        ]);
    }

    /**
     * Tambah item menu atau grup pilar baru.
     */
    public function store(Request $request)
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);

        $validated = $request->validate([
            'title'               => 'required|string|max:120',
            'description'         => 'nullable|string|max:255',
            'route_or_url'        => 'nullable|string|max:255',
            'icon'                => 'nullable|string|max:60',
            'parent_id'           => 'nullable|exists:menu_items,id',
            'menu_location'       => 'required|in:sidebar,security_tabs',
            'order_index'         => 'nullable|integer|min:0|max:999',
            'permission_required' => 'nullable|string|max:255',
            'role_required'       => 'nullable|string|max:255',
            'badge_text'          => 'nullable|string|max:30',
            'badge_key'           => 'nullable|string|max:60',
            'badge_color'         => 'nullable|string|max:150',
            'exact_match'         => 'nullable|boolean',
            'is_active'           => 'nullable|boolean',
        ]);

        $routeOrUrl = !empty($validated['route_or_url']) ? trim($validated['route_or_url']) : null;
        $matchPatterns = $routeOrUrl ? [$routeOrUrl] : null;

        // Auto-determine next order_index if not supplied
        $orderIndex = $validated['order_index'] ?? (
            (int) MenuItem::where('menu_location', $validated['menu_location'])
                ->where('parent_id', $validated['parent_id'] ?? null)
                ->max('order_index') + 1
        );

        $menu = MenuItem::create([
            'title'               => trim($validated['title']),
            'description'         => $validated['description'] ?? null,
            'route_or_url'        => $routeOrUrl,
            'match_patterns'      => $matchPatterns,
            'exact_match'         => (bool) ($validated['exact_match'] ?? false),
            'icon'                => $validated['icon'] ?: 'Layers',
            'parent_id'           => $validated['parent_id'] ?: null,
            'menu_location'       => $validated['menu_location'],
            'order_index'         => $orderIndex,
            'permission_required' => !empty($validated['permission_required']) ? trim($validated['permission_required']) : null,
            'role_required'       => !empty($validated['role_required']) ? trim($validated['role_required']) : null,
            'badge_text'          => !empty($validated['badge_text']) ? trim($validated['badge_text']) : null,
            'badge_key'           => !empty($validated['badge_key']) ? trim($validated['badge_key']) : null,
            'badge_color'         => !empty($validated['badge_color']) ? trim($validated['badge_color']) : null,
            'is_active'           => (bool) ($validated['is_active'] ?? true),
            'is_system'           => false,
        ]);

        AuditLog::create([
            'polda_id'     => $poldaId,
            'user_id'      => auth()->id(),
            'action'       => 'menu.create',
            'subject_type' => 'menu_item',
            'subject_id'   => $menu->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => ['title' => $menu->title, 'route' => $menu->route_or_url, 'location' => $menu->menu_location],
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "✓ Menu '{$menu->title}' berhasil ditambahkan ke navigasi dinamis.");
    }

    /**
     * Perbarui konfigurasi item menu atau pilar.
     */
    public function update(Request $request, $id)
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $menu = MenuItem::findOrFail($id);

        $validated = $request->validate([
            'title'               => 'required|string|max:120',
            'description'         => 'nullable|string|max:255',
            'route_or_url'        => 'nullable|string|max:255',
            'icon'                => 'nullable|string|max:60',
            'parent_id'           => 'nullable|exists:menu_items,id',
            'menu_location'       => 'required|in:sidebar,security_tabs',
            'order_index'         => 'required|integer|min:0|max:999',
            'permission_required' => 'nullable|string|max:255',
            'role_required'       => 'nullable|string|max:255',
            'badge_text'          => 'nullable|string|max:30',
            'badge_key'           => 'nullable|string|max:60',
            'badge_color'         => 'nullable|string|max:150',
            'exact_match'         => 'nullable|boolean',
            'is_active'           => 'nullable|boolean',
        ]);

        // Prevent setting parent_id to itself
        $parentId = !empty($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        if ($parentId === (int) $menu->id) {
            return redirect()->back()->with('error', 'Menu tidak dapat menjadi induk bagi dirinya sendiri.');
        }

        $routeOrUrl = !empty($validated['route_or_url']) ? trim($validated['route_or_url']) : null;
        $matchPatterns = $menu->match_patterns;
        if ($routeOrUrl && $routeOrUrl !== $menu->route_or_url) {
            $matchPatterns = [$routeOrUrl];
        }

        $menu->update([
            'title'               => trim($validated['title']),
            'description'         => $validated['description'] ?? null,
            'route_or_url'        => $routeOrUrl,
            'match_patterns'      => $matchPatterns,
            'exact_match'         => (bool) ($validated['exact_match'] ?? $menu->exact_match),
            'icon'                => $validated['icon'] ?: 'Layers',
            'parent_id'           => $parentId,
            'menu_location'       => $validated['menu_location'],
            'order_index'         => (int) $validated['order_index'],
            'permission_required' => !empty($validated['permission_required']) ? trim($validated['permission_required']) : null,
            'role_required'       => !empty($validated['role_required']) ? trim($validated['role_required']) : null,
            'badge_text'          => !empty($validated['badge_text']) ? trim($validated['badge_text']) : null,
            'badge_key'           => !empty($validated['badge_key']) ? trim($validated['badge_key']) : null,
            'badge_color'         => !empty($validated['badge_color']) ? trim($validated['badge_color']) : null,
            'is_active'           => (bool) ($validated['is_active'] ?? $menu->is_active),
        ]);

        AuditLog::create([
            'polda_id'     => $poldaId,
            'user_id'      => auth()->id(),
            'action'       => 'menu.update',
            'subject_type' => 'menu_item',
            'subject_id'   => $menu->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => ['title' => $menu->title, 'route' => $menu->route_or_url, 'order' => $menu->order_index],
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "✓ Konfigurasi menu '{$menu->title}' berhasil diperbarui.");
    }

    /**
     * Aktifkan / nonaktifkan visibilitas menu secara instan.
     */
    public function toggle(Request $request, $id)
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $menu = MenuItem::findOrFail($id);

        $menu->update([
            'is_active' => !$menu->is_active,
        ]);

        AuditLog::create([
            'polda_id'     => $poldaId,
            'user_id'      => auth()->id(),
            'action'       => 'menu.toggle',
            'subject_type' => 'menu_item',
            'subject_id'   => $menu->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => ['title' => $menu->title, 'is_active' => $menu->is_active],
            'created_at'   => now(),
        ]);

        $statusLabel = $menu->is_active ? 'DIAKTIFKAN' : 'DINONAKTIFKAN';
        return redirect()->back()->with('success', "✓ Menu '{$menu->title}' berhasil {$statusLabel}.");
    }

    /**
     * Ubah urutan (order_index) menu ke atas atau ke bawah dalam grupnya.
     */
    public function move(Request $request, $id)
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $menu = MenuItem::findOrFail($id);
        $direction = $request->input('direction', 'up'); // 'up' | 'down'

        $siblings = MenuItem::where('menu_location', $menu->menu_location)
            ->where('parent_id', $menu->parent_id)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->values();

        // Normalize sibling order indices 1..N
        foreach ($siblings as $idx => $item) {
            $item->order_index = $idx + 1;
            $item->saveQuietly();
        }

        $currentIndex = $siblings->search(fn($s) => $s->id === $menu->id);
        if ($currentIndex !== false) {
            $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
            if ($targetIndex >= 0 && $targetIndex < $siblings->count()) {
                $swapItem = $siblings[$targetIndex];
                $tempOrder = $menu->order_index;
                $menu->update(['order_index' => $swapItem->order_index]);
                $swapItem->update(['order_index' => $tempOrder]);
            }
        }

        return redirect()->back()->with('success', "✓ Urutan menu '{$menu->title}' berhasil diperbarui.");
    }

    /**
     * Hapus item menu dari basis data.
     */
    public function destroy(Request $request, $id)
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $menu = MenuItem::findOrFail($id);
        $title = $menu->title;

        $menu->delete();

        AuditLog::create([
            'polda_id'     => $poldaId,
            'user_id'      => auth()->id(),
            'action'       => 'menu.delete',
            'subject_type' => 'menu_item',
            'subject_id'   => $id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => ['title' => $title],
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "✓ Menu '{$title}' berhasil dihapus.");
    }

    /**
     * Pulihkan struktur menu ke standar baku SIMOD Enterprise (4 Pilar & 10 Sub-Tab).
     */
    public function resetDefaults(Request $request)
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);

        MenuItem::resetToDefaults();

        AuditLog::create([
            'polda_id'     => $poldaId,
            'user_id'      => auth()->id(),
            'action'       => 'menu.reset_defaults',
            'subject_type' => 'menu_item',
            'subject_id'   => 0,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => ['status' => 'factory_reset'],
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', '✓ Struktur menu berhasil dipulihkan ke konfigurasi standar SIMOD Enterprise.');
    }
}

