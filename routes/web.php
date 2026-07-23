<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBranchController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeviceController;
use App\Http\Controllers\Admin\AdminLicenseController;
use App\Http\Controllers\Admin\AdminRolePermissionController;
use App\Http\Controllers\Admin\AdminStaffController;
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

use App\Http\Controllers\StaffProfileController;

// --- PORTAL 1: RESTORAN KASA & POS GİRİŞİ ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\CheckActionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\HallController;

use App\Http\Controllers\QuickSaleController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ReportController;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/staff/profiles', [StaffProfileController::class, 'index'])->name('staff.profiles');
    Route::post('/staff/select', [StaffProfileController::class, 'selectProfile'])->name('staff.select');
    Route::get('/staff/switch', [StaffProfileController::class, 'switchProfile'])->name('staff.switch');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // --- HIZLI SATIŞ ROTALARI ---
    Route::middleware('staff.permission:hizli-satis')->controller(QuickSaleController::class)->prefix('quick-sale')->name('quicksale.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/transfer-table', 'transferToTable')->name('transfer');
    });

    // --- MUTFAK EKRANI ROTALARI ---
    Route::middleware('staff.permission:mutfak')->controller(KitchenController::class)->prefix('kitchen')->name('kitchen.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/poll', 'poll')->name('poll');
        Route::post('/{check}/send', 'sendToKitchen')->name('send');
        Route::post('/items/{item}/status', 'updateItemStatus')->name('items.status');
        Route::post('/{check}/status', 'updateCheckKitchenStatus')->name('status');
        Route::post('/{check}/complete', 'completeCheckKitchen')->name('complete');
    });

    // --- STOK YÖNETİMİ ROTALARI ---
    Route::middleware('staff.permission:stoklar')->controller(StockController::class)->prefix('stocks')->name('stocks.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/{product}', 'updateStock')->name('update');
        Route::post('/movements/{movement}/approve', 'approveReturn')->name('approve');
        Route::post('/movements/{movement}/reject', 'rejectReturn')->name('reject');
    });

    // --- RAPORLAR & GÜN SONU ROTALARI ---
    Route::middleware('staff.permission:raporlar')->controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'index')->name('index');
    });

    // --- SALON YÖNETİMİ ROTALARI ---
    Route::middleware('staff.permission:masalar')->controller(HallController::class)->prefix('halls')->name('halls.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::delete('/{hall}', 'destroy')->name('destroy');
    });

    // --- AYARLAR ROTALARI ---
    Route::middleware('staff.permission:ayarlar')->controller(SettingController::class)->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'update')->name('update');
    });

    // --- ÜRÜN & KATEGORİ YÖNETİMİ ROTALARI ---
    Route::middleware('staff.permission:urunler')->controller(ProductController::class)->prefix('products')->name('products.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{product}', 'update')->name('update');
        Route::delete('/{product}', 'destroy')->name('destroy');
        Route::post('/{product}/toggle', 'toggleStatus')->name('toggle');
        Route::post('/categories', 'storeCategory')->name('categories.store');
    });

    // --- MASA YÖNETİMİ & POS ADİSYON ROTALARI ---
    Route::middleware('staff.permission:masalar')->controller(DiningTableController::class)->prefix('tables')->name('tables.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{table}', 'show')->name('show');
        Route::patch('/{table}', 'update')->name('update');
        Route::delete('/{table}', 'destroy')->name('destroy');
    });

    Route::controller(CheckController::class)->prefix('checks')->name('checks.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('/{check}/items', 'addItems')->name('items.store');
        Route::delete('/{check}/items/{item}', 'removeItem')->name('items.destroy');
        Route::post('/{check}/close', 'close')->name('close');
    });

    Route::controller(CheckActionController::class)->prefix('checks/{check}/actions')->name('checks.actions.')->group(function () {
        Route::post('/treat', 'treat')->name('treat');
        Route::post('/void', 'void')->name('void');
        Route::post('/discount', 'discount')->name('discount');
        Route::post('/split', 'split')->name('split');
        Route::post('/merge', 'merge')->name('merge');
        Route::post('/move', 'move')->name('move');
    });
});

// --- PORTAL 2: CENTRAL ADMIN & LİSANS YÖNETİMİ GİRİŞİ ---
Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
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

        // Personel & Alt Üyelik Profilleri Yönetimi
        Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [AdminStaffController::class, 'update'])->name('staff.update');
        Route::post('/staff/{staff}/toggle', [AdminStaffController::class, 'toggleStatus'])->name('staff.toggle');
        Route::delete('/staff/{staff}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');

        // Rol & Modül Yetki Tanımları
        Route::get('/roles', [AdminRolePermissionController::class, 'index'])->name('roles.index');
        Route::post('/roles', [AdminRolePermissionController::class, 'update'])->name('roles.update');
        Route::post('/roles/create', [AdminRolePermissionController::class, 'storeRole'])->name('roles.store');
    });
});

/*
|--------------------------------------------------------------------------
| Windows C# Device Service & Print Spooler API Endpoints
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\PrintApiController;

Route::prefix('api/v1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/license/verify', [LicenseApiController::class, 'verifyLicense']);
    Route::post('/device/ping', [LicenseApiController::class, 'heartbeat']);

    // Termal Fiş Yazıcı Servisi API Rotaları (Windows C# Agent & Web POS)
    Route::prefix('print')->group(function () {
        Route::get('/pending', [PrintApiController::class, 'getPendingJobs']);
        Route::get('/jobs/{job}/status', [PrintApiController::class, 'getJobStatus']);
        Route::post('/jobs/{job}/status', [PrintApiController::class, 'updateJobStatus']);
        Route::post('/kitchen-slip/{check}', [PrintApiController::class, 'printKitchenSlip']);
        Route::post('/check-slip/{check}', [PrintApiController::class, 'printCheckSlip']);
        Route::get('/printers', [PrintApiController::class, 'getPrinters']);
        Route::post('/printers', [PrintApiController::class, 'savePrinter']);
    });
});

