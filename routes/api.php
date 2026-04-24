<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Reports
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports/map', [ReportController::class, 'mapIndex']); // must be before {report}
    Route::get('/reports/{report}', [ReportController::class, 'show']);
    Route::patch('/reports/{report}', [ReportController::class, 'update']);
    Route::delete('/reports/{report}', [ReportController::class, 'destroy']);
    Route::patch('/reports/{report}/status', [ReportController::class, 'updateStatus']);

    // Comments
    Route::get('/reports/{report}/comments', [CommentController::class, 'index']);
    Route::post('/reports/{report}/comments', [CommentController::class, 'store']);

    // Admin-only routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{user}/role', [AdminController::class, 'updateRole']);
        Route::patch('/users/{user}/toggle', [AdminController::class, 'toggleActive']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::get('/reports', [AdminController::class, 'reports']);
        Route::delete('/reports/{report}', [AdminController::class, 'deleteReport']);
        Route::post('/reports/{report}/restore', [AdminController::class, 'restoreReport']);
        Route::get('/analytics', [AdminController::class, 'analytics']);
        Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
    });
});