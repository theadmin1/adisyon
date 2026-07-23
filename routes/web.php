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

// --- GİZLİ OTOMATİK MİGRATİON & SEEDİNG TETİKLEYİCİ ---
Route::get('/system/auto-migrate-secret-run-99', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = \Illuminate\Support\Facades\Artisan::output();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $seedOutput = \Illuminate\Support\Facades\Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Migration ve Seeding Başarıyla Tamamlandı!',
            'migrate_output' => $migrateOutput,
            'seed_output' => $seedOutput,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

use App\Http\Controllers\StaffProfileController;

// --- PORTAL 1: RESTORAN KASA & POS GİRİŞİ ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/staff/profiles', [StaffProfileController::class, 'index'])->name('staff.profiles');
    Route::post('/staff/select', [StaffProfileController::class, 'selectProfile'])->name('staff.select');
    Route::get('/staff/switch', [StaffProfileController::class, 'switchProfile'])->name('staff.switch');
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
Route::prefix('api/v1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/license/verify', [LicenseApiController::class, 'verifyLicense']);
    Route::post('/device/ping', [LicenseApiController::class, 'heartbeat']);
});
