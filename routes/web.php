<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBranchController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeviceController;
use App\Http\Controllers\Admin\AdminLicenseController;
use App\Http\Controllers\Admin\AdminRolePermissionController;
use App\Http\Controllers\Admin\AdminSecurityLogController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Api\LicenseApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashShiftController;
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

Route::get('/sync', function () {
    return redirect()->route('admin.sync.index');
});

use App\Http\Controllers\StaffProfileController;

// --- PORTAL 1: RESTORAN KASA & POS GİRİŞİ ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.store');
});

use App\Http\Controllers\CheckActionController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\HallController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\PrinterSettingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchasingController;
use App\Http\Controllers\QuickSaleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierPortalController;

Route::controller(SupplierPortalController::class)
    ->prefix('supplier-portal')
    ->name('supplier-portal.')
    ->group(function () {
        Route::get('/{token}', 'show')->middleware('throttle:60,1')->name('show');
        Route::post('/{token}/verify', 'verify')->middleware('throttle:5,1')->name('verify');
        Route::post('/{token}/products', 'submitProducts')->middleware('throttle:20,1')->name('products.store');
        Route::post('/{token}/logout', 'logout')->name('logout');
    });

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'restaurant.user'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/staff/profiles', [StaffProfileController::class, 'index'])->name('staff.profiles');
    Route::post('/staff/select', [StaffProfileController::class, 'selectProfile'])->middleware('throttle:10,1')->name('staff.select');
    Route::post('/staff/switch', [StaffProfileController::class, 'switchProfile'])->name('staff.switch');
    // --- PAKET SERVİS & ONLINE ENTEGRASYON ROTALARI ---
    Route::middleware('staff.permission:paket-servis')->controller(DeliveryController::class)->prefix('delivery')->name('delivery.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/history', 'history')->name('history');
        Route::post('/phone-order', 'storePhoneOrder')->name('phone.store');
        Route::post('/{order}/status', 'updateStatus')->name('status.update');
        Route::post('/integrations', 'updateIntegrations')->name('integrations.update');
        Route::post('/toggle-channel', 'toggleChannelStatus')->name('toggle_channel');
        Route::post('/toggle-auto-accept', 'toggleAutoAccept')->name('toggle_auto_accept');
        Route::post('/simulate', 'simulateOrder')->name('simulate');
        Route::post('/clear-test', 'clearTestOrders')->name('clear_test');
    });

    // --- HIZLI SATIŞ ROTALARI ---
    Route::middleware('staff.permission:hizli-satis')->controller(QuickSaleController::class)->prefix('quick-sale')->name('quicksale.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/transfer-table', 'transferToTable')->name('transfer');
        Route::get('/recent-sales', 'recentSales')->name('recent-sales');
        Route::post('/hold', 'holdSale')->name('hold');
        Route::get('/sales/{check}', 'showSale')->name('show-sale');
        Route::put('/sales/{check}', 'updateSale')->name('update-sale');
        Route::post('/sales/{check}/cancel', 'cancelSale')->name('cancel-sale');
        Route::post('/sales/{check}/reopen', 'reopenSale')->name('reopen-sale');
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

    // --- TEDARİKÇİ & SATIN ALMA ROTALARI ---
    Route::middleware('staff.permission:satinalma')->controller(PurchasingController::class)->prefix('purchasing')->name('purchasing.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/orders/{purchaseOrder}', 'show')->name('show');
        Route::post('/suppliers', 'storeSupplier')->name('suppliers.store');
        Route::put('/suppliers/{supplier}', 'updateSupplier')->name('suppliers.update');
        Route::post('/suppliers/{supplier}/toggle', 'toggleSupplier')->name('suppliers.toggle');
        Route::post('/orders', 'storeOrder')->name('orders.store');
        Route::post('/orders/{purchaseOrder}/place', 'placeOrder')->name('orders.place');
        Route::post('/orders/{purchaseOrder}/receive', 'receive')->name('orders.receive');
        Route::post('/orders/{purchaseOrder}/cancel', 'cancel')->name('orders.cancel');
    });
    Route::middleware('staff.permission:satinalma')->controller(SupplierPortalController::class)->prefix('purchasing/supplier-portal')->name('purchasing.supplier-portal.')->group(function () {
        Route::post('/suppliers/{supplier}/setup', 'setup')->name('setup');
        Route::post('/suppliers/{supplier}/regenerate', 'regenerate')->name('regenerate');
        Route::post('/suppliers/{supplier}/toggle', 'toggle')->name('toggle');
        Route::post('/submissions/{supplierProductSubmission}/approve', 'approve')->name('approve');
        Route::post('/submissions/{supplierProductSubmission}/reject', 'reject')->name('reject');
    });

    // --- RAPORLAR & GÜN SONU ROTALARI ---
    Route::middleware('staff.permission:raporlar')->controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'index')->name('index');
    });

    // --- KASA VARDİYASI & SAYIM ROTALARI ---
    Route::middleware('staff.permission:kasa')->controller(CashShiftController::class)->prefix('cash-shifts')->name('cash-shifts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{cashShift}/movements', 'movement')->name('movements.store');
        Route::post('/{cashShift}/close', 'close')->name('close');
    });

    // --- SALON YÖNETİMİ ROTALARI ---
    Route::middleware('staff.permission:masalar')->controller(HallController::class)->prefix('halls')->name('halls.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::patch('/{hall}', 'update')->name('update');
        Route::delete('/{hall}', 'destroy')->name('destroy');
    });

    // --- AYARLAR ROTALARI ---
    Route::middleware('staff.permission:ayarlar')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');

        // Yazdırma kuyruğu yönetimi.
        // Yazıcı TANIMLARI burada değil, cihazdaki servis programında yapılır
        // (kurulu Windows yazıcısını yalnızca cihaz bilebilir).
        Route::controller(PrinterSettingController::class)->prefix('printers')->name('printers.')->group(function () {
            Route::post('/jobs/{job}/requeue', 'requeue')->name('jobs.requeue');
        });
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

    Route::middleware('staff.permission:masalar')->controller(CheckController::class)->prefix('checks')->name('checks.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('/{check}/items', 'addItems')->name('items.store');
        Route::delete('/{check}/items/{item}', 'removeItem')->name('items.destroy');
        Route::post('/{check}/close', 'close')->name('close');
        Route::post('/{check}/reopen', 'reopen')->name('reopen');
    });

    Route::middleware('staff.permission:masalar')->controller(CheckActionController::class)->prefix('checks/{check}/actions')->name('checks.actions.')->group(function () {
        Route::post('/treat', 'treat')->name('treat');
        Route::post('/void', 'void')->name('void');
        Route::post('/discount', 'discount')->name('discount');
        Route::post('/split', 'split')->name('split');
        Route::post('/merge', 'merge')->name('merge');
        Route::post('/move', 'move')->name('move');
    });
});

// --- PORTAL 2: CENTRAL ADMIN & LİSANS YÖNETİMİ GİRİŞİ ---
use App\Http\Controllers\Admin\AdminSyncController;
use App\Http\Controllers\Api\SyncApiController;

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
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
        Route::get('/logs', [AdminSecurityLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/export', [AdminSecurityLogController::class, 'export'])->name('logs.export');

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

        // Çevrimdışı Veri & Senkronizasyon Monitörü
        Route::get('/sync', [AdminSyncController::class, 'index'])->name('sync.index');
        Route::get('/updates', [AdminSyncController::class, 'updatesIndex'])->name('updates.index');
        Route::post('/sync/clear-logs', [AdminSyncController::class, 'clearLogs'])->name('sync.clear-logs');
        Route::post('/sync/update-system', [AdminSyncController::class, 'runSystemUpdate'])->name('sync.update-system');
        Route::post('/sync/verify-database-sync', [AdminSyncController::class, 'verifyDatabaseSync'])->name('sync.verify-database');
    });
});

/*
|--------------------------------------------------------------------------
| Windows C# Device Service & Print Spooler API Endpoints
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\PrintApiController;
use App\Http\Controllers\Api\TrendyolGoController;
use App\Http\Controllers\Api\UpdateApiController;
use App\Http\Controllers\Api\YemeksepetiController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

/*
 * A) Cihaz (Windows C# Servisi) uçları.
 *    Tarayıcı oturumu yoktur; CSRF muaftır ama X-Device-Api-Key ZORUNLUDUR.
 *    Şube kimliği istekten değil, doğrulanan cihazdan okunur.
 */
Route::withoutMiddleware([VerifyCsrfToken::class])->group(function () {
    Route::post('/license/verify', [LicenseApiController::class, 'verifyLicense'])->middleware('throttle:5,1');
    Route::post('/api/license/verify', [LicenseApiController::class, 'verifyLicense'])->middleware('throttle:5,1');
    Route::post('/api/v1/license/verify', [LicenseApiController::class, 'verifyLicense'])->middleware('throttle:5,1');
});

Route::prefix('api/v1')->group(function () {
    Route::post('/device/ping', [LicenseApiController::class, 'heartbeat'])->middleware('throttle:120,1');
    Route::post('/sync/push', [SyncApiController::class, 'pushOfflineData'])->middleware(['device.api', 'throttle:30,1']);
    Route::get('/sync/pull', [SyncApiController::class, 'pullSyncData'])->middleware(['device.api', 'throttle:30,1']);

    // 🛵 TRENDYOL GO INTEGRATION ENDPOINTS
    Route::post('/integrations/trendyol-go/webhook', [TrendyolGoController::class, 'handleWebhook'])
        ->middleware(['webhook.signature:trendyol', 'throttle:120,1']);
    Route::post('/integrations/trendyol-go/test-order', [TrendyolGoController::class, 'simulateTestOrder'])
        ->middleware(['auth', 'staff.permission:paket-servis']);

    // 🍕 YEMEKSEPETI (DELIVERY HERO) INTEGRATION ENDPOINTS
    Route::post('/integrations/yemeksepeti/webhook', [YemeksepetiController::class, 'handleWebhook'])
        ->middleware(['webhook.signature:yemeksepeti', 'throttle:120,1']);
    Route::post('/integrations/yemeksepeti/test-order', [YemeksepetiController::class, 'simulateTestOrder'])
        ->middleware(['auth', 'staff.permission:paket-servis']);

    // 🚀 SOFTWARE & DATABASE UPDATE ENDPOINTS FOR C# APP & OFFLINE SYSTEM
    Route::middleware(['device.api', 'throttle:10,1'])->group(function () {
        Route::get('/update/check', [UpdateApiController::class, 'checkUpdate']);
        Route::get('/update/download-package', [UpdateApiController::class, 'downloadPackage'])->name('api.update.download_package');
        Route::get('/update/download-database', [UpdateApiController::class, 'downloadDatabaseSnapshot'])->name('api.update.download_database');
    });

    Route::prefix('print')->middleware('device.api')->group(function () {
        Route::get('/pending', [PrintApiController::class, 'getPendingJobs']);
        Route::post('/jobs/{job}/claim', [PrintApiController::class, 'claimJob']);
        Route::post('/jobs/{job}/status', [PrintApiController::class, 'updateJobStatus']);
        Route::get('/printers', [PrintApiController::class, 'getPrinters']);
        Route::post('/printers', [PrintApiController::class, 'savePrinter']);
    });
});

/*
 * B) Web POS (tarayıcı) uçları.
 *    Oturum + CSRF korumalıdır; fiş oluşturma yetkisi kasa kullanıcısına aittir.
 */
Route::prefix('api/v1/print')->middleware('auth')->group(function () {
    Route::get('/jobs/{job}/status', [PrintApiController::class, 'getJobStatus']);
    Route::post('/kitchen-slip/{check}', [PrintApiController::class, 'printKitchenSlip']);
    Route::post('/check-slip/{check}', [PrintApiController::class, 'printCheckSlip']);
});
