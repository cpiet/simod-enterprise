<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\PressReleaseController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

// Dashboard & Intelligence Routes
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

// Issues Management Routes
Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
Route::post('/issues', [IssueController::class, 'store'])->name('issues.store');
Route::post('/issues/{id}/clarify', [IssueController::class, 'updateClarification'])->name('issues.clarify');

// Press Release Routes
Route::post('/press-releases', [PressReleaseController::class, 'store'])->name('press-releases.store');
