<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminApiTrafficController;
use App\Http\Controllers\Admin\AdminBranchController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeviceController;
use App\Http\Controllers\Admin\AdminLicenseController;
use App\Http\Controllers\Admin\AdminRolePermissionController;
use App\Http\Controllers\Admin\AdminSecurityLogController;
use App\Http\Controllers\Admin\AdminChainController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Api\LicenseApiController;
use App\Http\Controllers\Api\Waiter\TableLockController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashShiftController;
use App\Http\Controllers\CatalogRealtimeController;
use App\Http\Controllers\PublicDijiMenuController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Chain\ChainAuthController;
use App\Http\Controllers\Chain\ChainDashboardController;
use App\Http\Controllers\Chain\ChainDijiMenuController;
use App\Http\Controllers\Chain\ChainBranchController;
use App\Http\Controllers\Chain\ChainReportController;
use App\Http\Controllers\Chain\ChainMenuController;
use App\Http\Controllers\Chain\ChainStockController;
use App\Http\Controllers\Chain\ChainPurchasingController;
use App\Http\Controllers\Chain\ChainWorkflowController;

/*
|--------------------------------------------------------------------------
| Adisyon Restoran & Central Admin Portal Rotaları
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/qr-menu/{companySlug}/{branchSlug}', [PublicDijiMenuController::class, 'show'])
    ->where(['companySlug' => '[a-z0-9-]+', 'branchSlug' => '[a-z0-9-]+'])
    ->middleware('throttle:120,1')
    ->name('diji-menu.public');
Route::get('/qr-menu/{companySlug}/{branchSlug}/table/{tableToken}', [PublicDijiMenuController::class, 'show'])
    ->where(['companySlug' => '[a-z0-9-]+', 'branchSlug' => '[a-z0-9-]+', 'tableToken' => '[a-z0-9]{32}'])
    ->middleware('throttle:120,1')
    ->name('diji-menu.table');
Route::post('/qr-menu/{companySlug}/{branchSlug}/table/{tableToken}/orders', [PublicDijiMenuController::class, 'order'])
    ->where(['companySlug' => '[a-z0-9-]+', 'branchSlug' => '[a-z0-9-]+', 'tableToken' => '[a-z0-9]{32}'])
    ->middleware('throttle:10,1')
    ->name('diji-menu.orders.store');

Route::get('/sync', function () {
    return redirect()->route('admin.sync.index');
});

use App\Http\Controllers\StaffProfileController;

// --- PORTAL 1: RESTORAN KASA & POS GİRİŞİ ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:restaurant-login')->name('login.store');
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
use App\Http\Controllers\WaiterController;
use App\Http\Controllers\ProductionWorkflowController;

Route::controller(SupplierPortalController::class)
    ->prefix('supplier-portal')
    ->name('supplier-portal.')
    ->group(function () {
        Route::get('/{token}', 'show')->middleware('throttle:supplier-portal')->name('show');
        Route::post('/{token}/verify', 'verify')->middleware('throttle:supplier-verify')->name('verify');
        Route::post('/{token}/products', 'submitProducts')->middleware('throttle:supplier-submit')->name('products.store');
        Route::post('/{token}/logout', 'logout')->name('logout');
    });

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// --- PORTAL 3: RESTORAN / CAFE ZİNCİR YÖNETİMİ ---
Route::prefix('chain')->name('chain.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [ChainAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [ChainAuthController::class, 'login'])->middleware('throttle:chain-login')->name('login.store');
    });

    Route::middleware(['auth', 'chain.user'])->group(function () {
        Route::get('/dashboard', [ChainDashboardController::class, 'index'])->name('dashboard');
        Route::get('/branches', [ChainBranchController::class, 'index'])->name('branches.index');
        Route::post('/branches/{branch}/table-categories', [ChainBranchController::class, 'storeHall'])->name('branches.table-categories.store');
        Route::put('/branches/{branch}/table-categories/{hall}', [ChainBranchController::class, 'updateHall'])->name('branches.table-categories.update');
        Route::delete('/branches/{branch}/table-categories/{hall}', [ChainBranchController::class, 'destroyHall'])->name('branches.table-categories.destroy');
        Route::post('/branches/{branch}/tables', [ChainBranchController::class, 'storeTable'])->name('branches.tables.store');
        Route::put('/branches/{branch}/tables/{table}', [ChainBranchController::class, 'updateTable'])->name('branches.tables.update');
        Route::patch('/branches/{branch}/tables/{table}/name', [ChainBranchController::class, 'renameTable'])->name('branches.tables.rename');
        Route::patch('/branches/{branch}/tables/{table}/toggle', [ChainBranchController::class, 'toggleTable'])->name('branches.tables.toggle');
        Route::delete('/branches/{branch}/tables/{table}', [ChainBranchController::class, 'destroyTable'])->name('branches.tables.destroy');
        Route::get('/reports', [ChainReportController::class, 'index'])->name('reports.index');
        Route::get('/menu', [ChainMenuController::class, 'index'])->name('menu.index');
        Route::get('/diji-menu', [ChainDijiMenuController::class, 'index'])->name('diji-menu.index');
        Route::put('/diji-menu', [ChainDijiMenuController::class, 'update'])->name('diji-menu.update');
        Route::post('/menu/categories', [ChainMenuController::class, 'storeCategory'])->name('menu.categories.store');
        Route::post('/menu/products', [ChainMenuController::class, 'storeProduct'])->name('menu.products.store');
        Route::put('/menu/products/{menuProduct}', [ChainMenuController::class, 'updateProduct'])->name('menu.products.update');
        Route::post('/menu/products/{menuProduct}/publish', [ChainMenuController::class, 'publish'])->name('menu.products.publish');
        Route::get('/stocks', [ChainStockController::class, 'index'])->name('stocks.index');
        Route::post('/stocks/adjustments', [ChainStockController::class, 'adjust'])->name('stocks.adjust');
        Route::post('/stocks/central/adjustments', [ChainStockController::class, 'adjustCentral'])->name('stocks.central.adjust');
        Route::post('/stocks/central/distributions', [ChainStockController::class, 'distributeCentral'])->name('stocks.central.distribute');
        Route::get('/workflows', [ChainWorkflowController::class, 'index'])->name('workflows.index');
        Route::post('/workflows/recipes', [ChainWorkflowController::class, 'storeRecipe'])->name('workflows.recipes.store');
        Route::post('/workflows', [ChainWorkflowController::class, 'storeWorkflow'])->name('workflows.store');
        Route::post('/workflows/{workflow}/start', [ChainWorkflowController::class, 'start'])->name('workflows.start');
        Route::post('/workflows/{workflow}/complete', [ChainWorkflowController::class, 'complete'])->name('workflows.complete');
        Route::post('/workflows/{workflow}/cancel', [ChainWorkflowController::class, 'cancel'])->name('workflows.cancel');
        Route::post('/stock-transfers', [ChainStockController::class, 'store'])->name('stock-transfers.store');
        Route::post('/stock-transfers/{transfer}/approve', [ChainStockController::class, 'approve'])->name('stock-transfers.approve');
        Route::post('/stock-transfers/{transfer}/ship', [ChainStockController::class, 'ship'])->name('stock-transfers.ship');
        Route::post('/stock-transfers/{transfer}/receive', [ChainStockController::class, 'receive'])->name('stock-transfers.receive');
        Route::post('/stock-transfers/{transfer}/cancel', [ChainStockController::class, 'cancel'])->name('stock-transfers.cancel');
        Route::get('/purchasing', [ChainPurchasingController::class, 'index'])->name('purchasing.index');
        Route::post('/purchasing/suppliers', [ChainPurchasingController::class, 'storeSupplier'])->name('purchasing.suppliers.store');
        Route::post('/purchasing/orders', [ChainPurchasingController::class, 'storeOrder'])->name('purchasing.orders.store');
        Route::post('/purchasing/orders/{order}/place', [ChainPurchasingController::class, 'place'])->name('purchasing.orders.place');
        Route::post('/purchasing/orders/{order}/receive', [ChainPurchasingController::class, 'receive'])->name('purchasing.orders.receive');
        Route::post('/purchasing/orders/{order}/cancel', [ChainPurchasingController::class, 'cancel'])->name('purchasing.orders.cancel');
        Route::post('/logout', [ChainAuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth', 'restaurant.user'])->group(function () {
    Route::prefix('api/v1/waiter')->middleware('throttle:waiter-api')->group(function () {
        Route::post('/tables/{table}/lock', [TableLockController::class, 'lock'])->whereNumber('table');
        Route::post('/tables/{table}/unlock', [TableLockController::class, 'unlock'])->whereNumber('table');
    });

    Route::get('/catalog/version', [CatalogRealtimeController::class, 'version'])
        ->middleware('throttle:180,1')
        ->name('catalog.version');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/staff/profiles', [StaffProfileController::class, 'index'])->name('staff.profiles');
    Route::post('/staff/select', [StaffProfileController::class, 'selectProfile'])->middleware('throttle:staff-select')->name('staff.select');
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

    // --- GARSON OPERASYON EKRANI ---
    Route::middleware('staff.permission:garson')->controller(WaiterController::class)->prefix('waiter')->name('waiter.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/checks/{check}', 'show')->name('checks.show');
        Route::post('/checks/{check}/items', 'addItems')->name('checks.items.store');
        Route::put('/checks/{check}/customer-notes', 'updateCustomerNotes')->name('checks.notes.update');
        Route::post('/checks/{check}/request-payment', 'requestPayment')->name('checks.request-payment');
        Route::post('/checks/{check}/send-kitchen', [KitchenController::class, 'sendToKitchen'])->name('checks.send-kitchen');
    });

    // --- STOK YÖNETİMİ ROTALARI ---
    Route::middleware('staff.permission:stoklar')->controller(StockController::class)->prefix('stocks')->name('stocks.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/{product}', 'updateStock')->name('update');
        Route::post('/movements/{movement}/approve', 'approveReturn')->name('approve');
        Route::post('/movements/{movement}/reject', 'rejectReturn')->name('reject');
    });

    Route::middleware('staff.permission:is-akisi')->controller(ProductionWorkflowController::class)->prefix('workflows')->name('workflows.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/recipes', 'storeRecipe')->name('recipes.store');
        Route::post('/', 'storeWorkflow')->name('store');
        Route::post('/{workflow}/start', 'start')->name('start');
        Route::post('/{workflow}/complete', 'complete')->name('complete');
        Route::post('/{workflow}/cancel', 'cancel')->name('cancel');
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
        Route::get('/{product}/edit-data', 'editData')->name('edit-data');
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
        Route::get('/{table}/state', 'state')->name('state');
        Route::post('/{table}/editor-lock', 'acquireEditorLock')->name('editor-lock');
        Route::post('/{table}/editor-unlock', 'releaseEditorLock')->name('editor-unlock');
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
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login')->name('login.store');
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
        Route::put('/branches/{branch}', [AdminBranchController::class, 'update'])->name('branches.update');
        Route::post('/branches/{branch}/toggle', [AdminBranchController::class, 'toggleStatus'])->name('branches.toggle');
        Route::post('/branches/{branch}/reset-password', [AdminBranchController::class, 'resetPassword'])->name('branches.reset-password');
        Route::delete('/branches/{branch}', [AdminBranchController::class, 'destroy'])->name('branches.destroy');

        // Zincirler ve zincir paneli kullanıcıları
        Route::get('/chains', [AdminChainController::class, 'index'])->name('chains.index');
        Route::post('/chains', [AdminChainController::class, 'storeOrganization'])->name('chains.store');
        Route::put('/chains/{organization}', [AdminChainController::class, 'updateOrganization'])->name('chains.update');
        Route::post('/chain-users', [AdminChainController::class, 'storeUser'])->name('chain-users.store');
        Route::put('/chain-users/{user}', [AdminChainController::class, 'updateUser'])->name('chain-users.update');
        Route::delete('/chain-users/{user}', [AdminChainController::class, 'destroyUser'])->name('chain-users.destroy');

        // Cihazlar & Loglar
        Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices.index');
        Route::get('/logs', [AdminSecurityLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/terminal-stream', [AdminSecurityLogController::class, 'terminalStream'])->name('logs.terminal-stream');
        Route::get('/logs/export', [AdminSecurityLogController::class, 'export'])->name('logs.export');
        Route::get('/api-traffic', [AdminApiTrafficController::class, 'index'])->name('api-traffic.index');
        Route::get('/api-traffic/stream', [AdminApiTrafficController::class, 'stream'])->name('api-traffic.stream');

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
    Route::post('/license/verify', [LicenseApiController::class, 'verifyLicense'])->middleware('throttle:license-verify');
    Route::post('/api/license/verify', [LicenseApiController::class, 'verifyLicense'])->middleware('throttle:license-verify');
    Route::post('/api/v1/license/verify', [LicenseApiController::class, 'verifyLicense'])->middleware('throttle:license-verify');
});

Route::prefix('api/v1')->group(function () {
    Route::post('/device/ping', [LicenseApiController::class, 'heartbeat'])->middleware('throttle:device-heartbeat');
    Route::post('/sync/push', [SyncApiController::class, 'pushOfflineData'])->middleware(['device.api', 'throttle:device-sync']);
    Route::get('/sync/pull', [SyncApiController::class, 'pullSyncData'])->middleware(['device.api', 'throttle:device-sync']);

    // 🛵 TRENDYOL GO INTEGRATION ENDPOINTS
    Route::post('/integrations/trendyol-go/webhook', [TrendyolGoController::class, 'handleWebhook'])
        ->middleware(['webhook.signature:trendyol', 'throttle:delivery-webhook']);
    Route::post('/integrations/trendyol-go/test-order', [TrendyolGoController::class, 'simulateTestOrder'])
        ->middleware(['auth', 'staff.permission:paket-servis']);

    // 🍕 YEMEKSEPETI (DELIVERY HERO) INTEGRATION ENDPOINTS
    Route::post('/integrations/yemeksepeti/webhook', [YemeksepetiController::class, 'handleWebhook'])
        ->middleware(['webhook.signature:yemeksepeti', 'throttle:delivery-webhook']);
    Route::post('/integrations/yemeksepeti/test-order', [YemeksepetiController::class, 'simulateTestOrder'])
        ->middleware(['auth', 'staff.permission:paket-servis']);

    // 🚀 SOFTWARE & DATABASE UPDATE ENDPOINTS FOR C# APP & OFFLINE SYSTEM
    Route::middleware(['device.api', 'throttle:device-update'])->group(function () {
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
