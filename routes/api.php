<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- استيراد الـ Controllers القائمة سابقاً ---
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\DashboardController;

// --- استيراد الـ Controllers الخاصة بالنظام المالي والمخزني ---
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\TreasuryController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\PendingInvoiceItemController;

// [تعديل التوافق]: استيراد متحكمات العملاء والموردين المفقودة
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SupplierController;

// --- استيراد متحكمات قسم التقارير الموحد المحدثة ---
use App\Http\Controllers\Api\Reports\InventoryReportController;
use App\Http\Controllers\Api\Reports\FinancialReportController;
use App\Http\Controllers\Api\OpeningStockController;
use App\Http\Controllers\Api\TechnicianSaleController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- المسارات العامة (Public Routes) ---
Route::post('/login', [AuthController::class, 'login']);

// --- المسارات المحمية (Protected Routes) ---
Route::middleware('auth:sanctum')->group(function () {

    // 1. لوحة التحكم (الإحصائيات)
    Route::get('/manager/dashboard/stats', [DashboardController::class, 'getStats']);

    // 2. إدارة النسخ الاحتياطي
 // 2. إدارة النسخ الاحتياطي (نسخ، استعادة، وسحب آمن)
    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->middleware('can:backup.view');
        Route::post('/', [BackupController::class, 'store'])->middleware('can:backup.create');
        Route::get('/download-url', [BackupController::class, 'getDownloadUrl'])->middleware('can:backup.download');
        Route::get('/download', [BackupController::class, 'download'])->name('download'); // التحقق هنا ذاتي ومحمي بالتوقيع الرقمي المؤقت
        Route::post('/restore', [BackupController::class, 'restore'])->middleware('can:backup.restore');
        Route::delete('/', [BackupController::class, 'destroy'])->middleware('can:backup.delete');
    });

    // 3. إدارة المستخدمين والأدوار
    Route::apiResource('users', UserController::class);
    Route::get('roles/permissions', [RoleController::class, 'getAllPermissions'])->name('roles.permissions');
    Route::apiResource('roles', RoleController::class);

    // -------------------------------------------------------------
    // --- موديول الحسابات والمالية (المالية، الخزائن, البنوك، القيود) ---
    // -------------------------------------------------------------

    // 4. إدارة شجرة الحسابات وكشوفات الحساب الأساسية
    Route::get('accounts/{account}/statement', [AccountController::class, 'statement'])
         ->name('accounts.statement')
         ->middleware('can:report.account_statement');

    Route::apiResource('accounts', AccountController::class);

    // 5. إدارة الخزائن المالية (الصناديق)
    Route::apiResource('treasuries', TreasuryController::class);

    // 6. إدارة الحسابات البنكية للمنشأة
    Route::apiResource('banks', BankController::class);

    // 7. إدارة بنود وأنواع المصروفات
    Route::apiResource('expenses', ExpenseController::class);

    // [تعديل التوافق]: إضافة مسارات موديول العملاء والموردين لربطها بالواجهات الأمامية والحساب المجمع
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);

    // 8. إدارة القيود اليومية وسندات الصرف والقبض المركبة
    Route::apiResource('journal-entries', JournalEntryController::class)->parameters([
        'journal-entries' => 'journal_entry'
    ]);

    // -------------------------------------------------------------
    // --- موديول المخازن والأصناف (المستودعات والبنية التحتية) ---
    // -------------------------------------------------------------
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('units', UnitController::class);

    // 9. إدارة المخازن والمستودعات
    Route::apiResource('stores', StoreController::class);

    // جلب فئات الأسعار لتغذية مصفوفة الأصناف ديناميكياً
Route::get('price-lists', [PriceListController::class, 'index']);

   // 10. إدارة دليل الأصناف والخدمات والمواد الخام
    Route::post('items/refresh-stock', [ItemController::class, 'refreshStock'])->name('items.refresh_stock');
    Route::put('items/{id}/reorder-level', [ItemController::class, 'updateReorderLevel']);
    Route::apiResource('items', ItemController::class);

    // موديول استقبال واستبقاء السطور الصوتية والنصية للفحص والمعاينة
    Route::apiResource('pending-invoice-items', PendingInvoiceItemController::class)->only([
        'index', 'store'
    ]);

    // مسارات موديول المشتريات ومردوداتها المدمج (تغطي الإضافة، التعديل الفوري، الحذف، والاستعراض)
    Route::apiResource('purchases', PurchaseController::class);

    // مسارات موديول المبيعات ومرتجع الكاشير المدمج (تغطي الإضافة، التعديل الفوري، الحذف، والاستعراض)
    Route::apiResource('sales', SaleController::class);

    Route::prefix('technician')->name('technician.')->group(function () {
        Route::get('sales', [TechnicianSaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [TechnicianSaleController::class, 'show'])->name('sales.show');
        Route::patch('sales/{sale}/swap-raw-materials', [TechnicianSaleController::class, 'swapRawMaterials'])->name('sales.swap_raw_materials');
    });

    Route::apiResource('opening-stocks', OpeningStockController::class);

    Route::apiResource('vouchers', \App\Http\Controllers\Api\VoucherController::class);

    // موديول التسويات الجردية المستقل لإدارة كميات المستودعات وفروقات جرد الأصناف
    Route::apiResource('stock-adjustments', \App\Http\Controllers\Api\StockAdjustmentController::class)->parameters([
        'stock-adjustments' => 'stock_adjustment'
    ]);

    // -------------------------------------------------------------
    // --- قسم التقارير الموحد والمعزول (Reports Module) ---
    // -------------------------------------------------------------
    Route::prefix('reports')->group(function () {

        // أ. تقارير المخازن والمستودعات اللوجستية التفصيلية
        Route::get('inventory/current-stock', [InventoryReportController::class, 'currentStock']);
        Route::get('inventory/stock-card', [InventoryReportController::class, 'stockCard']);
        Route::get('inventory/valuation', [InventoryReportController::class, 'stockValuation']);
        Route::get('inventory/adjustments-summary', [InventoryReportController::class, 'adjustmentsSummary']);

        // ب. التقارير الماليّة والمحاسبيّة الحركية والقوائم الختامية
        Route::get('financial/account-ledger', [FinancialReportController::class, 'accountLedger']);
        Route::get('financial/sub-ledger', [FinancialReportController::class, 'subLedgerStatement']);
        Route::get('financial/trial-balance', [FinancialReportController::class, 'trialBalance']);
        Route::get('financial/income-statement', [FinancialReportController::class, 'incomeStatement']);
        Route::get('financial/balance-sheet', [FinancialReportController::class, 'balanceSheet']);

    });

    // -------------------------------------------------------------

    // 11. بيانات المستخدم الحالي وتسجيل الخروج
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load('roles:id,name', 'roles.permissions:id,name');
        return response()->json($user);
    });
    Route::post('/logout', [AuthController::class, 'logout']);

});
