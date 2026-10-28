<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\MediaIntelligenceController;
use App\Http\Controllers\PressReleaseController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\SystemModuleController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

// Dashboard & Media Intelligence
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/media-intelligence', [MediaIntelligenceController::class, 'index'])->name('media-intelligence');
Route::post('/media-intelligence/ai-briefing', [MediaIntelligenceController::class, 'storeAiBriefing'])->name('media-intelligence.briefing');
Route::post('/media-intelligence/rss-sources', [MediaIntelligenceController::class, 'storeRssSource'])->name('media-intelligence.rss.store');
Route::post('/media-intelligence/rss-sources/{id}/toggle', [MediaIntelligenceController::class, 'toggleRssSource'])->name('media-intelligence.rss.toggle');
Route::delete('/media-intelligence/rss-sources/{id}', [MediaIntelligenceController::class, 'deleteRssSource'])->name('media-intelligence.rss.delete');
Route::post('/media-intelligence/rss-sources/test', [MediaIntelligenceController::class, 'testRssSource'])->name('media-intelligence.rss.test');

// Issues Management
Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
Route::post('/issues', [IssueController::class, 'store'])->name('issues.store');
Route::post('/issues/{id}/clarify', [IssueController::class, 'updateClarification'])->name('issues.clarify');

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
Route::get('/reports', [SystemModuleController::class, 'reports'])->name('reports.index');
Route::get('/personalia', [SystemModuleController::class, 'personalia'])->name('personalia.index');
Route::get('/audit-logs', [SystemModuleController::class, 'auditLogs'])->name('audit-logs.index');
Route::get('/profile', [SystemModuleController::class, 'profile'])->name('profile.index');
Route::get('/settings/polda', [SystemModuleController::class, 'poldaSettings'])->name('settings.polda');
Route::get('/security', [SystemModuleController::class, 'securityCenter'])->name('security.center');
Route::get('/roles', [SystemModuleController::class, 'rolesMatrix'])->name('roles.matrix');
Route::get('/settings/mail', [SystemModuleController::class, 'mailSettings'])->name('settings.mail');
Route::get('/database/backup', [SystemModuleController::class, 'databaseBackup'])->name('database.backup');
Route::get('/maintenance', [SystemModuleController::class, 'systemMaintenance'])->name('system.maintenance');
