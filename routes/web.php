<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBranchController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeviceController;
use App\Http\Controllers\Admin\AdminLicenseController;
use App\Http\Controllers\Api\LicenseApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Adisyon Restoran & Central Admin Portal Rotaları
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// --- PORTAL 1: RESTORAN KASA & POS GİRİŞİ ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// --- PORTAL 2: CENTRAL ADMIN & LİSANS YÖNETİMİ GİRİŞİ ---
Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
    });

    Route::middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        // Lisans Yönetimi
        Route::get('/licenses', [AdminLicenseController::class, 'index'])->name('licenses.index');
        Route::post('/licenses', [AdminLicenseController::class, 'store'])->name('licenses.store');
        Route::post('/licenses/{license}/toggle', [AdminLicenseController::class, 'toggleStatus'])->name('licenses.toggle');
        Route::delete('/licenses/{license}', [AdminLicenseController::class, 'destroy'])->name('licenses.destroy');

        // Şube Yönetimi
        Route::get('/branches', [AdminBranchController::class, 'index'])->name('branches.index');
        Route::post('/branches', [AdminBranchController::class, 'store'])->name('branches.store');

        // Cihazlar & Loglar
        Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices.index');
        Route::get('/logs', [AdminDeviceController::class, 'logs'])->name('logs.index');
    });
});

/*
|--------------------------------------------------------------------------
| Windows C# Device Service API Endpoints (Lisans Doğrulama & Heartbeat)
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')->group(function () {
    Route::post('/license/verify', [LicenseApiController::class, 'verifyLicense']);
    Route::post('/device/ping', [LicenseApiController::class, 'heartbeat']);
});
