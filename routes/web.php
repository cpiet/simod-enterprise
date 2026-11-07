<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\MediaIntelligenceController;
use App\Http\Controllers\MenuManagementController;
use App\Http\Controllers\PressReleaseController;
use App\Http\Controllers\SadewaController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\SocialIntelligenceController;
use App\Http\Controllers\SystemModuleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// Authentication Routes (Protected by 'guest' middleware for login & session invalidation for logout)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
    Route::post('/login/pin', [\App\Http\Controllers\Auth\QuickLoginController::class, 'login'])->middleware('throttle:10,1')->name('login.pin');
    Route::post('/login/forget-device', [\App\Http\Controllers\Auth\QuickLoginController::class, 'forget'])->middleware('throttle:10,1')->name('login.forget-device');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Seluruh rute di bawah ini wajib login (middleware 'auth' -> redirect ke /login).
Route::middleware(['auth', 'idle'])->group(function () {

// Multi-Tenant Context Switcher (CSRF Protected & RBAC Enforced)
Route::post('/tenant/switch', [TenantController::class, 'switchTenant'])->name('tenant.switch');

// Dashboard & Media Intelligence
Route::get('/', [DashboardController::class, 'index'])->middleware('module:dashboard_view')->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('module:dashboard_view')->name('dashboard.index');
Route::get('/command-center', [DashboardController::class, 'index'])->middleware('module:dashboard_view')->name('command-center');
Route::get('/media-intelligence', [MediaIntelligenceController::class, 'index'])->middleware('module:media_intelligence_view')->name('media-intelligence');
Route::get('/monitoring-media-online', [MediaIntelligenceController::class, 'index'])->middleware('module:media_intelligence_view')->name('monitoring-media-online');
Route::post('/media-intelligence/articles/{id}/bookmark', [MediaIntelligenceController::class, 'toggleBookmark'])->middleware('module:media_intelligence_view')->name('media-intelligence.bookmark');
Route::post('/media-intelligence/articles/bookmarks/import', [MediaIntelligenceController::class, 'importBookmarks'])->middleware('module:media_intelligence_view')->name('media-intelligence.bookmark.import');
Route::post('/media-intelligence/analyze-gemini', [MediaIntelligenceController::class, 'analyzeGemini'])->middleware(['module:media_intelligence_ai', 'throttle:10,1'])->name('media-intelligence.analyze.gemini');
Route::middleware('module:media_intelligence_view|social_intelligence_view|issues_view')->prefix('sadewa')->name('sadewa.')->group(function () {
    Route::get('/meta', [SadewaController::class, 'meta'])->name('meta');
    Route::get('/conversations', [SadewaController::class, 'conversations'])->name('conversations');
    Route::get('/conversations/{id}', [SadewaController::class, 'show'])->whereNumber('id')->name('show');
    Route::delete('/conversations/{id}', [SadewaController::class, 'destroy'])->whereNumber('id')->name('destroy');
    Route::delete('/conversations', [SadewaController::class, 'destroyAll'])->name('destroy-all');
    Route::post('/chat', [SadewaController::class, 'chat'])->middleware('throttle:20,1')->name('chat');
});
Route::post('/media-intelligence/gemini-settings', [MediaIntelligenceController::class, 'updateGeminiSettings'])->middleware('module:media_intelligence_ai')->name('media-intelligence.gemini.settings');
Route::post('/media-intelligence/gemini-test', [MediaIntelligenceController::class, 'testGeminiConnection'])->middleware(['module:media_intelligence_ai', 'throttle:10,1'])->name('media-intelligence.gemini.test');

// Smart Dossier & Topik Atensi Pimpinan
Route::post('/media-intelligence/topics', [MediaIntelligenceController::class, 'storeTopic'])->middleware('module:media_intelligence_view')->name('media-intelligence.topics.store');
Route::post('/media-intelligence/topics/{id}/update', [MediaIntelligenceController::class, 'updateTopic'])->middleware('module:media_intelligence_view')->name('media-intelligence.topics.update');
Route::delete('/media-intelligence/topics/{id}', [MediaIntelligenceController::class, 'deleteTopic'])->middleware('module:media_intelligence_view')->name('media-intelligence.topics.delete');
Route::post('/media-intelligence/articles/{id}/toggle-topic', [MediaIntelligenceController::class, 'toggleArticleTopic'])->middleware('module:media_intelligence_view')->name('media-intelligence.articles.toggle-topic');
Route::post('/media-intelligence/topics/{id}/analyze-ai', [MediaIntelligenceController::class, 'analyzeTopicAi'])->middleware(['module:media_intelligence_ai', 'throttle:10,1'])->name('media-intelligence.topics.analyze-ai');
Route::post('/media-intelligence/topics/{id}/crawl-live', [MediaIntelligenceController::class, 'crawlTopicLive'])->middleware(['module:media_intelligence_ai', 'throttle:10,1'])->name('media-intelligence.topics.crawl-live');
Route::post('/media-intelligence/topics/adopt-suggestion', [MediaIntelligenceController::class, 'adoptSuggestedTopic'])->middleware(['module:media_intelligence_ai', 'throttle:10,1'])->name('media-intelligence.topics.adopt-suggestion');
Route::post('/media-intelligence/topics/dismiss-suggestion', [MediaIntelligenceController::class, 'dismissSuggestedTopic'])->middleware('module:media_intelligence_view')->name('media-intelligence.topics.dismiss-suggestion');

// Social Media Intelligence Radar
Route::get('/social-intelligence', [SocialIntelligenceController::class, 'index'])->middleware('module:social_intelligence_view')->name('social-intelligence.index');
Route::post('/social-intelligence/{id}/bookmark', [SocialIntelligenceController::class, 'toggleBookmark'])->middleware('module:social_intelligence_view')->name('social-intelligence.bookmark');
Route::post('/social-intelligence/sync', [SocialIntelligenceController::class, 'sync'])->middleware(['module:social_intelligence_sync', 'throttle:10,1'])->name('social-intelligence.sync');
// Pantau Akun lintas platform & Ringkasan Komentar (Apify berbayar → izin sinkron; ringkasan AI → kuota AI).
Route::post('/social-intelligence/accounts', [SocialIntelligenceController::class, 'addMonitoredAccount'])->middleware(['module:social_intelligence_sync', 'throttle:10,1']);
Route::post('/social-intelligence/accounts/{id}/sync', [SocialIntelligenceController::class, 'syncMonitoredAccount'])->middleware(['module:social_intelligence_sync', 'throttle:10,1']);
Route::delete('/social-intelligence/accounts/{id}', [SocialIntelligenceController::class, 'deleteMonitoredAccount'])->middleware('module:social_intelligence_sync');
Route::post('/social-intelligence/comments', [SocialIntelligenceController::class, 'fetchComments'])->middleware(['module:social_intelligence_sync', 'throttle:10,1']);
Route::post('/social-intelligence/comments/{id}/summarize', [SocialIntelligenceController::class, 'summarizeComments'])->middleware(['module:social_intelligence_view', 'throttle:10,1']);
Route::post('/social-intelligence/sync-trends', [SocialIntelligenceController::class, 'syncTrends'])->middleware(['module:social_intelligence_sync', 'throttle:10,1'])->name('social-intelligence.sync-trends');
Route::post('/social-intelligence/track-keyword', [SocialIntelligenceController::class, 'trackKeyword'])->middleware(['module:social_intelligence_sync', 'throttle:10,1'])->name('social-intelligence.track-keyword');
Route::post('/social-intelligence/analyze-ai', [SocialIntelligenceController::class, 'analyzeAI'])->middleware(['module:social_intelligence_view', 'throttle:10,1'])->name('social-intelligence.analyze-ai');
Route::get('/social-intelligence/reports/{id}', [SocialIntelligenceController::class, 'getReport'])->middleware('module:social_intelligence_view')->name('social-intelligence.reports.show');
Route::post('/social-intelligence/cases/{id}/close', [SocialIntelligenceController::class, 'closeCase'])->middleware('module:social_intelligence_view')->name('social-intelligence.cases.close');
Route::post('/social-intelligence/cases/{id}/refresh', [SocialIntelligenceController::class, 'refreshCase'])->middleware(['module:social_intelligence_sync', 'throttle:10,1'])->name('social-intelligence.cases.refresh');
Route::post('/social-intelligence/settings', [SocialIntelligenceController::class, 'updateSettings'])->middleware('module:social_intelligence_sync')->name('social-intelligence.settings');
Route::post('/social-intelligence/test-connection', [SocialIntelligenceController::class, 'testConnection'])->middleware(['module:social_intelligence_sync', 'throttle:10,1'])->name('social-intelligence.test-connection');

// Issues Management
Route::get('/issues', [IssueController::class, 'index'])->middleware('module:issues_view')->name('issues.index');
Route::get('/issues/create', [IssueController::class, 'create'])->middleware('module:issues_create')->name('issues.create');
Route::get('/issues-export-csv', [IssueController::class, 'exportCsv'])->middleware('module:issues_view')->name('issues.export');
Route::get('/issues/{id}', [IssueController::class, 'show'])->middleware('module:issues_view')->name('issues.show');
Route::post('/issues', [IssueController::class, 'store'])->middleware('module:issues_create')->name('issues.store');
Route::post('/issues/{id}/clarify', [IssueController::class, 'updateClarification'])->middleware('module:issues_verify')->name('issues.clarify');
Route::post('/issues/{id}/submit', [IssueController::class, 'submitDraft'])->middleware('module:issues_create')->name('issues.submit');
Route::post('/issues/{id}/verify', [IssueController::class, 'verify'])->middleware('module:issues_verify')->name('issues.verify');
Route::post('/issues/{id}/return', [IssueController::class, 'returnIssue'])->middleware('module:issues_verify')->name('issues.return');
Route::post('/issues/{id}/resolve', [IssueController::class, 'resolve'])->middleware('module:issues_verify')->name('issues.resolve');
Route::post('/issues/{id}/close', [IssueController::class, 'close'])->middleware('module:issues_verify')->name('issues.close');
Route::post('/issues/{id}/archive', [IssueController::class, 'archive'])->middleware('module:issues_verify')->name('issues.archive');
Route::post('/issues/{id}/restore', [IssueController::class, 'restore'])->middleware('module:issues_verify')->name('issues.restore');
Route::delete('/issues/{id}', [IssueController::class, 'destroy'])->middleware('module:issues_verify|issues_create')->name('issues.destroy');
Route::delete('/issues/{id}/force', [IssueController::class, 'forceDestroy'])->middleware('module:issues_verify')->name('issues.force-destroy');
Route::post('/issues-bulk', [IssueController::class, 'bulk'])->middleware(['module:issues_verify', 'throttle:30,1'])->name('issues.bulk');
Route::post('/issues/{id}/task', [IssueController::class, 'createTask'])->middleware('module:tasks_disposition')->name('issues.task');
Route::post('/issues/{id}/release', [IssueController::class, 'createRelease'])->middleware('module:issues_verify')->name('issues.release');

// Press Releases
Route::post('/press-releases', [PressReleaseController::class, 'store'])->middleware('module:issues_verify')->name('press-releases.store');

// Multi-Channel Publications
Route::get('/publications', [PublicationController::class, 'index'])->middleware('module:publications_manage')->name('publications.index');
Route::post('/publications', [PublicationController::class, 'store'])->middleware('module:publications_manage')->name('publications.store');
Route::post('/publications/{id}/approve', [PublicationController::class, 'approve'])->middleware('module:publications_manage')->name('publications.approve');
Route::post('/publications/{id}/reject', [PublicationController::class, 'reject'])->middleware('module:publications_manage')->name('publications.reject');

// Operational Tasks & Disposisi
Route::get('/tasks', [TaskController::class, 'index'])->middleware('module:tasks_execute|tasks_disposition')->name('tasks.index');
Route::post('/tasks', [TaskController::class, 'store'])->middleware('module:tasks_disposition')->name('tasks.store');
Route::post('/tasks/{id}/update', [TaskController::class, 'addUpdate'])->middleware('module:tasks_execute|tasks_disposition')->name('tasks.update');

// Extended Modules from SIMOD-HUMAS Parity
Route::get('/verification-queue', [SystemModuleController::class, 'verificationQueue'])->middleware('module:issues_view')->name('verification.queue');
Route::get('/gallery', [SystemModuleController::class, 'gallery'])->middleware('module:gallery_access')->name('gallery.index');
Route::post('/gallery', [SystemModuleController::class, 'storeMedia'])->middleware('module:gallery_access')->name('gallery.store');
Route::get('/reports', [SystemModuleController::class, 'reports'])->middleware('module:reports_export')->name('reports.index');
Route::get('/reports/export/csv', [SystemModuleController::class, 'exportReportsCsv'])->middleware('module:reports_export')->name('reports.export.csv');
Route::get('/personalia', [SystemModuleController::class, 'personalia'])->middleware('module:personalia_manage')->name('personalia.index');
Route::post('/personalia', [SystemModuleController::class, 'storeUser'])->middleware('module:personalia_manage')->name('personalia.store');
Route::put('/personalia/{id}', [SystemModuleController::class, 'updateUser'])->middleware('module:personalia_manage')->name('personalia.update');
Route::post('/personalia/{id}/toggle-status', [SystemModuleController::class, 'toggleUserStatus'])->middleware('module:personalia_manage')->name('personalia.toggle-status');
Route::post('/personalia/{id}/reset-password', [SystemModuleController::class, 'resetUserPassword'])->middleware('module:personalia_manage')->name('personalia.reset-password');
Route::delete('/personalia/{id}', [SystemModuleController::class, 'deleteUser'])->middleware('module:personalia_manage')->name('personalia.delete');
Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->middleware('module:security_center')->name('audit-logs.index');
Route::get('/audit-logs/export', [\App\Http\Controllers\AuditLogController::class, 'export'])->middleware(['module:security_center', 'throttle:10,1'])->name('audit-logs.export');
Route::get('/audit-logs/purge-preview', [\App\Http\Controllers\AuditLogController::class, 'purgePreview'])->middleware(['module:security_center', 'throttle:60,1'])->name('audit-logs.purge-preview');
Route::post('/audit-logs/purge', [\App\Http\Controllers\AuditLogController::class, 'purge'])->middleware(['module:security_center', 'throttle:10,1'])->name('audit-logs.purge');
Route::post('/audit-logs/retention', [\App\Http\Controllers\AuditLogController::class, 'updateRetention'])->middleware('module:security_center')->name('audit-logs.retention');
Route::get('/profile', [SystemModuleController::class, 'profile'])->name('profile.index');
Route::post('/profile/password', [SystemModuleController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password');
Route::post('/profile/pin', [\App\Http\Controllers\Auth\QuickLoginController::class, 'enroll'])->middleware('throttle:6,1')->name('profile.pin.enroll');
Route::delete('/profile/pin', [\App\Http\Controllers\Auth\QuickLoginController::class, 'revoke'])->middleware('throttle:6,1')->name('profile.pin.revoke');
Route::get('/settings/polda', [SystemModuleController::class, 'poldaSettings'])->middleware('module:polda_settings')->name('settings.polda');
Route::post('/settings/polda/update', [SystemModuleController::class, 'updatePolda'])->middleware('module:polda_settings')->name('settings.polda.update');
Route::post('/settings/polda/create', [SystemModuleController::class, 'storePolda'])->middleware('module:polda_settings')->name('settings.polda.create');
Route::post('/settings/polres/create', [SystemModuleController::class, 'storePolres'])->middleware('module:polda_settings')->name('settings.polres.create');
Route::get('/security', [SystemModuleController::class, 'securityCenter'])->middleware('module:security_center')->name('security.center');
Route::post('/security/national-brief', [SystemModuleController::class, 'toggleNationalBrief'])->middleware('module:security_center')->name('security.national-brief');
Route::post('/security', [SystemModuleController::class, 'updateSecurity'])->middleware('module:security_center')->name('security.update');
Route::get('/roles', [SystemModuleController::class, 'rolesMatrix'])->middleware('module:roles_matrix_manage')->name('roles.matrix');
Route::post('/roles', [SystemModuleController::class, 'updateRolesMatrix'])->middleware('module:roles_matrix_manage')->name('roles.update');
Route::post('/roles/reset', [SystemModuleController::class, 'resetRolesMatrix'])->middleware('module:roles_matrix_manage')->name('roles.reset');
// Database Backup & Restore Center
Route::get('/database/backup', [\App\Http\Controllers\DatabaseBackupController::class, 'index'])->middleware('module:database_backup')->name('database.backup');
Route::post('/database/backup/create', [\App\Http\Controllers\DatabaseBackupController::class, 'createBackup'])->middleware('module:database_backup')->name('database.backup.create');
Route::get('/database/backup/download/{filename}', [\App\Http\Controllers\DatabaseBackupController::class, 'download'])->middleware('module:database_backup')->name('database.backup.download');
Route::post('/database/backup/restore/{filename}', [\App\Http\Controllers\DatabaseBackupController::class, 'restore'])->middleware('module:database_backup')->name('database.backup.restore');
Route::post('/database/backup/upload-restore', [\App\Http\Controllers\DatabaseBackupController::class, 'uploadAndRestore'])->middleware('module:database_backup')->name('database.backup.upload-restore');
Route::delete('/database/backup/delete/{filename}', [\App\Http\Controllers\DatabaseBackupController::class, 'deleteBackup'])->middleware('module:database_backup')->name('database.backup.delete');
Route::post('/database/backup/settings', [\App\Http\Controllers\DatabaseBackupController::class, 'updateSettings'])->middleware('module:database_backup')->name('database.backup.settings');
Route::get('/maintenance/news-cleanup', [\App\Http\Controllers\NewsCleanupController::class, 'index'])->middleware('module:maintenance_ops')->name('news.cleanup');
Route::post('/maintenance/news-cleanup', [\App\Http\Controllers\NewsCleanupController::class, 'purge'])->middleware('module:maintenance_ops')->name('news.cleanup.purge');
Route::get('/maintenance/rss-sync', [\App\Http\Controllers\RssSyncController::class, 'index'])->middleware('module:maintenance_ops')->name('maintenance.rss-sync');
Route::post('/maintenance/rss-sync', [\App\Http\Controllers\RssSyncController::class, 'update'])->middleware('module:maintenance_ops')->name('maintenance.rss-sync.update');
Route::post('/maintenance/rss-sync/trigger', [\App\Http\Controllers\RssSyncController::class, 'triggerSync'])->middleware(['module:maintenance_ops', 'throttle:6,1'])->name('maintenance.rss-sync.trigger');
Route::post('/maintenance/rss-sync/feeds', [\App\Http\Controllers\RssSyncController::class, 'storeFeed'])->middleware('module:maintenance_ops')->name('maintenance.rss-sync.feeds.store');
Route::post('/maintenance/rss-sync/feeds/{id}/toggle', [\App\Http\Controllers\RssSyncController::class, 'toggleFeed'])->middleware('module:maintenance_ops')->name('maintenance.rss-sync.feeds.toggle');
Route::delete('/maintenance/rss-sync/feeds/{id}', [\App\Http\Controllers\RssSyncController::class, 'deleteFeed'])->middleware('module:maintenance_ops')->name('maintenance.rss-sync.feeds.delete');
Route::post('/maintenance/rss-sync/feeds/test', [\App\Http\Controllers\RssSyncController::class, 'testFeed'])->middleware(['module:maintenance_ops', 'throttle:20,1'])->name('maintenance.rss-sync.feeds.test');

// Mesin Intelijen AI & LLM Administration Center
Route::get('/settings/ai', [\App\Http\Controllers\AiSettingController::class, 'index'])->middleware('module:security_center')->name('settings.ai');
Route::post('/settings/ai', [\App\Http\Controllers\AiSettingController::class, 'update'])->middleware('module:security_center')->name('settings.ai.update');
Route::post('/settings/ai/test', [\App\Http\Controllers\AiSettingController::class, 'testConnection'])->middleware(['module:security_center', 'throttle:10,1'])->name('settings.ai.test');
Route::post('/settings/ai/clear-block', [\App\Http\Controllers\AiSettingController::class, 'clearBlock'])->middleware('module:security_center')->name('settings.ai.clear-block');
Route::post('/settings/ai/reset-prompt', [\App\Http\Controllers\AiSettingController::class, 'resetPrompt'])->middleware('module:security_center')->name('settings.ai.reset-prompt');
Route::post('/settings/ai/apify', [\App\Http\Controllers\AiSettingController::class, 'updateApify'])->middleware('module:security_center')->name('settings.ai.apify.update');
Route::post('/settings/ai/test-apify', [\App\Http\Controllers\AiSettingController::class, 'testApify'])->middleware(['module:security_center', 'throttle:10,1'])->name('settings.ai.apify.test');

// Manajemen Menu & Navigasi Dinamis (Database-Driven Menu Architecture)
Route::get('/settings/menus', [MenuManagementController::class, 'index'])->middleware('module:roles_matrix_manage')->name('settings.menus');
Route::post('/settings/menus', [MenuManagementController::class, 'store'])->middleware('module:roles_matrix_manage')->name('settings.menus.store');
Route::put('/settings/menus/{id}', [MenuManagementController::class, 'update'])->middleware('module:roles_matrix_manage')->name('settings.menus.update');
Route::post('/settings/menus/{id}/toggle', [MenuManagementController::class, 'toggle'])->middleware('module:roles_matrix_manage')->name('settings.menus.toggle');
Route::post('/settings/menus/{id}/move', [MenuManagementController::class, 'move'])->middleware('module:roles_matrix_manage')->name('settings.menus.move');
Route::delete('/settings/menus/{id}', [MenuManagementController::class, 'destroy'])->middleware('module:roles_matrix_manage')->name('settings.menus.delete');
Route::post('/settings/menus/reset', [MenuManagementController::class, 'resetDefaults'])->middleware('module:roles_matrix_manage')->name('settings.menus.reset');

});
