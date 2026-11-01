<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\MediaIntelligenceController;
use App\Http\Controllers\MenuManagementController;
use App\Http\Controllers\PressReleaseController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\SocialIntelligenceController;
use App\Http\Controllers\SystemModuleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// Authentication Routes (Protected by 'guest' middleware for login & session invalidation for logout)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout.get');

// Multi-Tenant Context Switcher (CSRF Protected & RBAC Enforced)
Route::post('/tenant/switch', [TenantController::class, 'switchTenant'])->name('tenant.switch');

// Dashboard & Media Intelligence
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/command-center', [DashboardController::class, 'index'])->name('command-center');
Route::get('/media-intelligence', [MediaIntelligenceController::class, 'index'])->name('media-intelligence');
Route::get('/monitoring-media-online', [MediaIntelligenceController::class, 'index'])->name('monitoring-media-online');
Route::post('/media-intelligence/ai-briefing', [MediaIntelligenceController::class, 'storeAiBriefing'])->name('media-intelligence.briefing');
Route::post('/media-intelligence/rss-sources', [MediaIntelligenceController::class, 'storeRssSource'])->name('media-intelligence.rss.store');
Route::post('/media-intelligence/rss-sources/{id}/toggle', [MediaIntelligenceController::class, 'toggleRssSource'])->name('media-intelligence.rss.toggle');
Route::delete('/media-intelligence/rss-sources/{id}', [MediaIntelligenceController::class, 'deleteRssSource'])->name('media-intelligence.rss.delete');
Route::post('/media-intelligence/articles/{id}/bookmark', [MediaIntelligenceController::class, 'toggleBookmark'])->name('media-intelligence.bookmark');
Route::post('/media-intelligence/articles/bookmarks/import', [MediaIntelligenceController::class, 'importBookmarks'])->name('media-intelligence.bookmark.import');
Route::post('/media-intelligence/rss-sources/test', [MediaIntelligenceController::class, 'testRssSource'])->name('media-intelligence.rss.test');
Route::post('/media-intelligence/analyze-gemini', [MediaIntelligenceController::class, 'analyzeGemini'])->name('media-intelligence.analyze.gemini');
Route::post('/media-intelligence/gemini-settings', [MediaIntelligenceController::class, 'updateGeminiSettings'])->name('media-intelligence.gemini.settings');
Route::post('/media-intelligence/gemini-test', [MediaIntelligenceController::class, 'testGeminiConnection'])->name('media-intelligence.gemini.test');

// Smart Dossier & Topik Atensi Pimpinan
Route::post('/media-intelligence/topics', [MediaIntelligenceController::class, 'storeTopic'])->name('media-intelligence.topics.store');
Route::post('/media-intelligence/topics/{id}/update', [MediaIntelligenceController::class, 'updateTopic'])->name('media-intelligence.topics.update');
Route::delete('/media-intelligence/topics/{id}', [MediaIntelligenceController::class, 'deleteTopic'])->name('media-intelligence.topics.delete');
Route::post('/media-intelligence/articles/{id}/toggle-topic', [MediaIntelligenceController::class, 'toggleArticleTopic'])->name('media-intelligence.articles.toggle-topic');
Route::post('/media-intelligence/topics/{id}/analyze-ai', [MediaIntelligenceController::class, 'analyzeTopicAi'])->name('media-intelligence.topics.analyze-ai');

// Social Media Intelligence Radar
Route::get('/social-intelligence', [SocialIntelligenceController::class, 'index'])->name('social-intelligence.index');
Route::post('/social-intelligence/{id}/bookmark', [SocialIntelligenceController::class, 'toggleBookmark'])->name('social-intelligence.bookmark');
Route::post('/social-intelligence/sync', [SocialIntelligenceController::class, 'sync'])->name('social-intelligence.sync');
Route::post('/social-intelligence/settings', [SocialIntelligenceController::class, 'updateSettings'])->name('social-intelligence.settings');
Route::post('/social-intelligence/test-connection', [SocialIntelligenceController::class, 'testConnection'])->name('social-intelligence.test-connection');

// Issues Management
Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
Route::get('/issues/create', [IssueController::class, 'create'])->name('issues.create');
Route::get('/issues-export-csv', [IssueController::class, 'exportCsv'])->name('issues.export');
Route::get('/issues/{id}', [IssueController::class, 'show'])->name('issues.show');
Route::post('/issues', [IssueController::class, 'store'])->name('issues.store');
Route::post('/issues/{id}/clarify', [IssueController::class, 'updateClarification'])->name('issues.clarify');
Route::post('/issues/{id}/verify', [IssueController::class, 'verify'])->name('issues.verify');
Route::post('/issues/{id}/return', [IssueController::class, 'returnIssue'])->name('issues.return');
Route::post('/issues/{id}/resolve', [IssueController::class, 'resolve'])->name('issues.resolve');
Route::post('/issues/{id}/close', [IssueController::class, 'close'])->name('issues.close');
Route::post('/issues/{id}/task', [IssueController::class, 'createTask'])->name('issues.task');
Route::post('/issues/{id}/release', [IssueController::class, 'createRelease'])->name('issues.release');

// Press Releases
Route::post('/press-releases', [PressReleaseController::class, 'store'])->name('press-releases.store');

// Multi-Channel Publications
Route::get('/publications', [PublicationController::class, 'index'])->name('publications.index');
Route::post('/publications', [PublicationController::class, 'store'])->name('publications.store');

// Operational Tasks & Disposisi
Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
Route::post('/tasks/{id}/update', [TaskController::class, 'addUpdate'])->name('tasks.update');

// Extended Modules from SIMOD-HUMAS Parity
Route::get('/verification-queue', [SystemModuleController::class, 'verificationQueue'])->name('verification.queue');
Route::get('/gallery', [SystemModuleController::class, 'gallery'])->name('gallery.index');
Route::post('/gallery', [SystemModuleController::class, 'storeMedia'])->name('gallery.store');
Route::get('/reports', [SystemModuleController::class, 'reports'])->name('reports.index');
Route::get('/reports/export/csv', [SystemModuleController::class, 'exportReportsCsv'])->name('reports.export.csv');
Route::get('/personalia', [SystemModuleController::class, 'personalia'])->name('personalia.index');
Route::post('/personalia', [SystemModuleController::class, 'storeUser'])->name('personalia.store');
Route::put('/personalia/{id}', [SystemModuleController::class, 'updateUser'])->name('personalia.update');
Route::post('/personalia/{id}/toggle-status', [SystemModuleController::class, 'toggleUserStatus'])->name('personalia.toggle-status');
Route::post('/personalia/{id}/reset-password', [SystemModuleController::class, 'resetUserPassword'])->name('personalia.reset-password');
Route::delete('/personalia/{id}', [SystemModuleController::class, 'deleteUser'])->name('personalia.delete');
Route::get('/audit-logs', [SystemModuleController::class, 'auditLogs'])->name('audit-logs.index');
Route::get('/profile', [SystemModuleController::class, 'profile'])->name('profile.index');
Route::get('/settings/polda', [SystemModuleController::class, 'poldaSettings'])->name('settings.polda');
Route::post('/settings/polda/update', [SystemModuleController::class, 'updatePolda'])->name('settings.polda.update');
Route::post('/settings/polda/create', [SystemModuleController::class, 'storePolda'])->name('settings.polda.create');
Route::post('/settings/polres/create', [SystemModuleController::class, 'storePolres'])->name('settings.polres.create');
Route::get('/security', [SystemModuleController::class, 'securityCenter'])->name('security.center');
Route::post('/security', [SystemModuleController::class, 'updateSecurity'])->name('security.update');
Route::get('/roles', [SystemModuleController::class, 'rolesMatrix'])->name('roles.matrix');
Route::post('/roles', [SystemModuleController::class, 'updateRolesMatrix'])->name('roles.update');
Route::post('/roles/reset', [SystemModuleController::class, 'resetRolesMatrix'])->name('roles.reset');
Route::get('/settings/mail', [SystemModuleController::class, 'mailSettings'])->name('settings.mail');
// Database Backup & Restore Center
Route::get('/database/backup', [\App\Http\Controllers\DatabaseBackupController::class, 'index'])->name('database.backup');
Route::post('/database/backup/create', [\App\Http\Controllers\DatabaseBackupController::class, 'createBackup'])->name('database.backup.create');
Route::get('/database/backup/download/{filename}', [\App\Http\Controllers\DatabaseBackupController::class, 'download'])->name('database.backup.download');
Route::post('/database/backup/restore/{filename}', [\App\Http\Controllers\DatabaseBackupController::class, 'restore'])->name('database.backup.restore');
Route::post('/database/backup/upload-restore', [\App\Http\Controllers\DatabaseBackupController::class, 'uploadAndRestore'])->name('database.backup.upload-restore');
Route::delete('/database/backup/delete/{filename}', [\App\Http\Controllers\DatabaseBackupController::class, 'deleteBackup'])->name('database.backup.delete');
Route::post('/database/backup/settings', [\App\Http\Controllers\DatabaseBackupController::class, 'updateSettings'])->name('database.backup.settings');
Route::get('/maintenance', [SystemModuleController::class, 'systemMaintenance'])->name('system.maintenance');
Route::get('/maintenance/news-cleanup', [\App\Http\Controllers\NewsCleanupController::class, 'index'])->name('news.cleanup');
Route::post('/maintenance/news-cleanup', [\App\Http\Controllers\NewsCleanupController::class, 'purge'])->name('news.cleanup.purge');
Route::get('/maintenance/rss-sync', [\App\Http\Controllers\RssSyncController::class, 'index'])->name('maintenance.rss-sync');
Route::post('/maintenance/rss-sync', [\App\Http\Controllers\RssSyncController::class, 'update'])->name('maintenance.rss-sync.update');
Route::post('/maintenance/rss-sync/trigger', [\App\Http\Controllers\RssSyncController::class, 'triggerSync'])->name('maintenance.rss-sync.trigger');

// Mesin Intelijen AI & LLM Administration Center
Route::get('/settings/ai', [\App\Http\Controllers\AiSettingController::class, 'index'])->name('settings.ai');
Route::post('/settings/ai', [\App\Http\Controllers\AiSettingController::class, 'update'])->name('settings.ai.update');
Route::post('/settings/ai/test', [\App\Http\Controllers\AiSettingController::class, 'testConnection'])->name('settings.ai.test');

// Manajemen Menu & Navigasi Dinamis (Database-Driven Menu Architecture)
Route::get('/settings/menus', [MenuManagementController::class, 'index'])->name('settings.menus');
Route::post('/settings/menus', [MenuManagementController::class, 'store'])->name('settings.menus.store');
Route::put('/settings/menus/{id}', [MenuManagementController::class, 'update'])->name('settings.menus.update');
Route::post('/settings/menus/{id}/toggle', [MenuManagementController::class, 'toggle'])->name('settings.menus.toggle');
Route::post('/settings/menus/{id}/move', [MenuManagementController::class, 'move'])->name('settings.menus.move');
Route::delete('/settings/menus/{id}', [MenuManagementController::class, 'destroy'])->name('settings.menus.delete');
Route::post('/settings/menus/reset', [MenuManagementController::class, 'resetDefaults'])->name('settings.menus.reset');
