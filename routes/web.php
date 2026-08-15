<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountingReportController;
use App\Http\Controllers\Accounting\FiscalPeriodController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\DepartmentController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\LeaveTypeController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\PositionController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Manufacturing\BomController;
use App\Http\Controllers\Manufacturing\ProductionOrderController;
use App\Http\Controllers\Master\PartnerController;
use App\Http\Controllers\Master\PaymentTermController;
use App\Http\Controllers\Master\PriceLevelController;
use App\Http\Controllers\Master\ProductCategoryController;
use App\Http\Controllers\Master\ProductController;
use App\Http\Controllers\Master\TaxController;
use App\Http\Controllers\Master\UomController;
use App\Http\Controllers\Master\WarehouseController;
use App\Http\Controllers\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseRequisitionController;
use App\Http\Controllers\Purchasing\SupplierPaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Sales\CustomerPaymentController;
use App\Http\Controllers\Sales\DeliveryOrderController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\SalesInvoiceController;
use App\Http\Controllers\Sales\SalesOrderController;
use App\Http\Controllers\Sales\SalesReturnController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\System\ActivityLogController;
use App\Http\Controllers\System\RoleController;
use App\Http\Controllers\System\SettingController;
use App\Http\Controllers\System\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
| `$resource` registers the seven CRUD routes with one permission per action,
| so navigation (config/erp.php) and authorisation always use the same names.
*/
Route::middleware(['auth', 'active'])->group(function () {

    $resource = function (
        string $uri,
        string $controller,
        string $permission,
        string $param = 'record',
        array $only = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'],
    ) {
        $has = fn (string $action) => in_array($action, $only, true);

        if ($has('index')) {
            Route::get($uri, [$controller, 'index'])->name("{$uri}.index")->middleware("permission:{$permission}.view");
        }
        if ($has('create')) {
            Route::get("{$uri}/create", [$controller, 'create'])->name("{$uri}.create")->middleware("permission:{$permission}.create");
        }
        if ($has('store')) {
            Route::post($uri, [$controller, 'store'])->name("{$uri}.store")->middleware("permission:{$permission}.create");
        }
        if ($has('show')) {
            Route::get("{$uri}/{{$param}}", [$controller, 'show'])->name("{$uri}.show")->middleware("permission:{$permission}.view");
        }
        if ($has('edit')) {
            Route::get("{$uri}/{{$param}}/edit", [$controller, 'edit'])->name("{$uri}.edit")->middleware("permission:{$permission}.edit");
        }
        if ($has('update')) {
            Route::put("{$uri}/{{$param}}", [$controller, 'update'])->name("{$uri}.update")->middleware("permission:{$permission}.edit");
        }
        if ($has('destroy')) {
            Route::delete("{$uri}/{{$param}}", [$controller, 'destroy'])->name("{$uri}.destroy")->middleware("permission:{$permission}.delete");
        }
    };

    Route::redirect('/', '/dashboard')->name('home');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard.view');
    Route::get('search', SearchController::class)->name('search');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    /* ---------------------------------------------------------------- Master */
    // Declared before the resource so `products/import` is not caught by `products/{product}`.
    Route::get('products/import', [ProductController::class, 'importForm'])
        ->name('products.import')->middleware('permission:product.create');
    Route::post('products/import', [ProductController::class, 'import'])
        ->name('products.import.store')->middleware('permission:product.create');
    Route::get('products/import/template', [ProductController::class, 'downloadTemplate'])
        ->name('products.template')->middleware('permission:product.create');
    Route::get('products/export', [ProductController::class, 'export'])
        ->name('products.export')->middleware('permission:product.view');

    $resource('products', ProductController::class, 'product', 'product');
    Route::get('api/products/search', [ProductController::class, 'search'])->name('products.search')->middleware('permission:product.view');

    $resource('categories', ProductCategoryController::class, 'category', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
    $resource('uoms', UomController::class, 'uom', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
    $resource('taxes', TaxController::class, 'tax', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
    $resource('price-levels', PriceLevelController::class, 'price-level', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
    $resource('payment-terms', PaymentTermController::class, 'payment-term', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
    $resource('warehouses', WarehouseController::class, 'warehouse', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
    $resource('partners', PartnerController::class, 'partner', 'partner');

    /* ------------------------------------------------------------ Purchasing */
    Route::middleware('module:purchase_requisition')->group(function () use ($resource) {
        $resource('purchase-requisitions', PurchaseRequisitionController::class, 'purchase-requisition', 'purchaseRequisition');
        Route::post('purchase-requisitions/{purchaseRequisition}/submit', [PurchaseRequisitionController::class, 'submit'])
            ->name('purchase-requisitions.submit')->middleware('permission:purchase-requisition.edit');
        Route::post('purchase-requisitions/{purchaseRequisition}/approve', [PurchaseRequisitionController::class, 'approve'])
            ->name('purchase-requisitions.approve')->middleware('permission:purchase-requisition.approve');
        Route::post('purchase-requisitions/{purchaseRequisition}/reject', [PurchaseRequisitionController::class, 'reject'])
            ->name('purchase-requisitions.reject')->middleware('permission:purchase-requisition.approve');

        Route::get('purchase-orders/from-requisition/{requisition}', [PurchaseOrderController::class, 'createFromRequisition'])
            ->name('purchase-orders.from-requisition')->middleware('permission:purchase-order.create');
    });

    Route::middleware('module:purchasing')->group(function () use ($resource) {
        $resource('purchase-orders', PurchaseOrderController::class, 'purchase-order', 'purchaseOrder');
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('purchase-orders.approve')->middleware('permission:purchase-order.approve');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('purchase-orders.cancel')->middleware('permission:purchase-order.approve');
        Route::post('purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])
            ->name('purchase-orders.close')->middleware('permission:purchase-order.approve');
        Route::get('purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])
            ->name('purchase-orders.print')->middleware('permission:purchase-order.view');

        $resource('goods-receipts', GoodsReceiptController::class, 'goods-receipt', 'goodsReceipt', ['index', 'create', 'store', 'show', 'destroy']);
        Route::post('goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])
            ->name('goods-receipts.post')->middleware('permission:goods-receipt.post');
        Route::post('goods-receipts/{goodsReceipt}/cancel', [GoodsReceiptController::class, 'cancel'])
            ->name('goods-receipts.cancel')->middleware('permission:goods-receipt.post');
        Route::get('goods-receipts/{goodsReceipt}/print', [GoodsReceiptController::class, 'print'])
            ->name('goods-receipts.print')->middleware('permission:goods-receipt.view');

        $resource('purchase-invoices', PurchaseInvoiceController::class, 'purchase-invoice', 'purchaseInvoice');
        Route::get('purchase-invoices/from-order/{purchaseOrder}', [PurchaseInvoiceController::class, 'createFromOrder'])
            ->name('purchase-invoices.from-order')->middleware('permission:purchase-invoice.create');
        Route::post('purchase-invoices/{purchaseInvoice}/post', [PurchaseInvoiceController::class, 'post'])
            ->name('purchase-invoices.post')->middleware('permission:purchase-invoice.post');
        Route::post('purchase-invoices/{purchaseInvoice}/cancel', [PurchaseInvoiceController::class, 'cancel'])
            ->name('purchase-invoices.cancel')->middleware('permission:purchase-invoice.post');
        Route::get('purchase-invoices/{purchaseInvoice}/print', [PurchaseInvoiceController::class, 'print'])
            ->name('purchase-invoices.print')->middleware('permission:purchase-invoice.view');

        $resource('supplier-payments', SupplierPaymentController::class, 'supplier-payment', 'payment', ['index', 'create', 'store', 'show', 'destroy']);
        Route::post('supplier-payments/{payment}/post', [SupplierPaymentController::class, 'post'])
            ->name('supplier-payments.post')->middleware('permission:supplier-payment.post');
        Route::post('supplier-payments/{payment}/cancel', [SupplierPaymentController::class, 'cancel'])
            ->name('supplier-payments.cancel')->middleware('permission:supplier-payment.post');
    });

    /* ----------------------------------------------------------------- Sales */
    Route::middleware('module:quotation')->group(function () use ($resource) {
        // Didaftarkan sebelum resource agar tidak tertangkap oleh quotations/{quotation}.
        Route::get('quotations/export', [QuotationController::class, 'exportList'])
            ->name('quotations.export')->middleware('permission:quotation.view');

        $resource('quotations', QuotationController::class, 'quotation', 'quotation');
        Route::get('quotations/{quotation}/excel', [QuotationController::class, 'excel'])
            ->name('quotations.excel')->middleware('permission:quotation.view');
        Route::post('quotations/{quotation}/transition', [QuotationController::class, 'transition'])
            ->name('quotations.transition')->middleware('permission:quotation.edit');
        Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])
            ->name('quotations.print')->middleware('permission:quotation.view');

        Route::get('sales-orders/from-quotation/{quotation}', [SalesOrderController::class, 'createFromQuotation'])
            ->name('sales-orders.from-quotation')->middleware('permission:sales-order.create');
    });

    Route::middleware('module:sales')->group(function () use ($resource) {
        $resource('sales-orders', SalesOrderController::class, 'sales-order', 'salesOrder');
        Route::post('sales-orders/{salesOrder}/approve', [SalesOrderController::class, 'approve'])
            ->name('sales-orders.approve')->middleware('permission:sales-order.approve');
        Route::post('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])
            ->name('sales-orders.cancel')->middleware('permission:sales-order.approve');
        Route::post('sales-orders/{salesOrder}/close', [SalesOrderController::class, 'close'])
            ->name('sales-orders.close')->middleware('permission:sales-order.approve');
        Route::get('sales-orders/{salesOrder}/print', [SalesOrderController::class, 'print'])
            ->name('sales-orders.print')->middleware('permission:sales-order.view');

        $resource('delivery-orders', DeliveryOrderController::class, 'delivery-order', 'deliveryOrder', ['index', 'create', 'store', 'show', 'destroy']);
        Route::post('delivery-orders/{deliveryOrder}/post', [DeliveryOrderController::class, 'post'])
            ->name('delivery-orders.post')->middleware('permission:delivery-order.post');
        Route::post('delivery-orders/{deliveryOrder}/cancel', [DeliveryOrderController::class, 'cancel'])
            ->name('delivery-orders.cancel')->middleware('permission:delivery-order.post');
        Route::get('delivery-orders/{deliveryOrder}/print', [DeliveryOrderController::class, 'print'])
            ->name('delivery-orders.print')->middleware('permission:delivery-order.view');

        // Didaftarkan sebelum resource agar tidak tertangkap sales-invoices/{salesInvoice}.
        Route::get('sales-invoices/select-deliveries', [SalesInvoiceController::class, 'selectDeliveries'])
            ->name('sales-invoices.select-deliveries')->middleware('permission:sales-invoice.create');
        Route::get('sales-invoices/from-deliveries', [SalesInvoiceController::class, 'createFromDeliveries'])
            ->name('sales-invoices.from-deliveries')->middleware('permission:sales-invoice.create');

        $resource('sales-invoices', SalesInvoiceController::class, 'sales-invoice', 'salesInvoice');
        Route::get('sales-invoices/from-order/{salesOrder}', [SalesInvoiceController::class, 'createFromOrder'])
            ->name('sales-invoices.from-order')->middleware('permission:sales-invoice.create');
        Route::post('sales-invoices/{salesInvoice}/post', [SalesInvoiceController::class, 'post'])
            ->name('sales-invoices.post')->middleware('permission:sales-invoice.post');
        Route::post('sales-invoices/{salesInvoice}/cancel', [SalesInvoiceController::class, 'cancel'])
            ->name('sales-invoices.cancel')->middleware('permission:sales-invoice.post');
        Route::get('sales-invoices/{salesInvoice}/print', [SalesInvoiceController::class, 'print'])
            ->name('sales-invoices.print')->middleware('permission:sales-invoice.view');

        $resource('customer-payments', CustomerPaymentController::class, 'customer-payment', 'payment', ['index', 'create', 'store', 'show', 'destroy']);
        Route::post('customer-payments/{payment}/post', [CustomerPaymentController::class, 'post'])
            ->name('customer-payments.post')->middleware('permission:customer-payment.post');
        Route::post('customer-payments/{payment}/cancel', [CustomerPaymentController::class, 'cancel'])
            ->name('customer-payments.cancel')->middleware('permission:customer-payment.post');

        $resource('sales-returns', SalesReturnController::class, 'sales-return', 'salesReturn',
            ['index', 'create', 'store', 'show', 'destroy']);
        Route::post('sales-returns/{salesReturn}/post', [SalesReturnController::class, 'post'])
            ->name('sales-returns.post')->middleware('permission:sales-return.post');
        Route::post('sales-returns/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])
            ->name('sales-returns.cancel')->middleware('permission:sales-return.post');
        Route::get('sales-returns/{salesReturn}/print', [SalesReturnController::class, 'print'])
            ->name('sales-returns.print')->middleware('permission:sales-return.view');
    });

    /* ------------------------------------------------------------- Inventory */
    Route::middleware('permission:stock.view')->group(function () {
        Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
        Route::get('stocks/card', [StockController::class, 'card'])->name('stocks.card');
        Route::get('stocks/movements', [StockController::class, 'movements'])->name('stocks.movements');
        Route::get('stocks/valuation', [StockController::class, 'valuation'])->name('stocks.valuation');
    });

    $resource('stock-transfers', StockTransferController::class, 'stock-transfer', 'stockTransfer');
    Route::post('stock-transfers/{stockTransfer}/post', [StockTransferController::class, 'post'])
        ->name('stock-transfers.post')->middleware('permission:stock-transfer.post');
    Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])
        ->name('stock-transfers.cancel')->middleware('permission:stock-transfer.post');

    $resource('stock-adjustments', StockAdjustmentController::class, 'stock-adjustment', 'stockAdjustment');
    Route::post('stock-adjustments/{stockAdjustment}/post', [StockAdjustmentController::class, 'post'])
        ->name('stock-adjustments.post')->middleware('permission:stock-adjustment.post');
    Route::post('stock-adjustments/{stockAdjustment}/cancel', [StockAdjustmentController::class, 'cancel'])
        ->name('stock-adjustments.cancel')->middleware('permission:stock-adjustment.post');
    Route::get('api/stock-lookup', [StockAdjustmentController::class, 'lookup'])
        ->name('stock-adjustments.lookup')->middleware('permission:stock-adjustment.create');
    Route::get('api/stock-load', [StockAdjustmentController::class, 'loadStock'])
        ->name('stock-adjustments.load')->middleware('permission:stock-adjustment.create');

    /* --------------------------------------------------------- Manufacturing */
    Route::middleware('module:manufacturing')->group(function () use ($resource) {
        $resource('boms', BomController::class, 'bom', 'bom');
        $resource('production-orders', ProductionOrderController::class, 'production-order', 'productionOrder', ['index', 'create', 'store', 'show', 'destroy']);
        Route::post('production-orders/{productionOrder}/release', [ProductionOrderController::class, 'release'])
            ->name('production-orders.release')->middleware('permission:production-order.post');
        Route::post('production-orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])
            ->name('production-orders.complete')->middleware('permission:production-order.post');
        Route::post('production-orders/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])
            ->name('production-orders.cancel')->middleware('permission:production-order.post');
        Route::get('api/boms/{bom}/detail', [ProductionOrderController::class, 'bomDetail'])
            ->name('boms.detail')->middleware('permission:production-order.create');
    });

    /* ------------------------------------------------------------ Accounting */
    Route::middleware('module:accounting')->group(function () use ($resource) {
        $resource('accounts', AccountController::class, 'account', 'account');

        $resource('journals', JournalController::class, 'journal', 'journal', ['index', 'create', 'store', 'show', 'destroy']);
        Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse'])
            ->name('journals.reverse')->middleware('permission:journal.post');

        Route::middleware('permission:accounting-report.view')->prefix('accounting')->name('accounting.')->group(function () {
            Route::get('ledger', [AccountingReportController::class, 'ledger'])->name('ledger');
            Route::get('trial-balance', [AccountingReportController::class, 'trialBalance'])->name('trial-balance');
            Route::get('income-statement', [AccountingReportController::class, 'incomeStatement'])->name('income-statement');
            Route::get('balance-sheet', [AccountingReportController::class, 'balanceSheet'])->name('balance-sheet');
        });

        Route::get('fiscal-periods', [FiscalPeriodController::class, 'index'])
            ->name('fiscal-periods.index')->middleware('permission:fiscal-period.view');
        Route::post('fiscal-periods/generate', [FiscalPeriodController::class, 'generate'])
            ->name('fiscal-periods.generate')->middleware('permission:fiscal-period.edit');
        Route::post('fiscal-periods/{fiscalPeriod}/toggle', [FiscalPeriodController::class, 'toggle'])
            ->name('fiscal-periods.toggle')->middleware('permission:fiscal-period.edit');
    });

    /* ------------------------------------------------------------------- SDM */
    Route::middleware('module:hr')->group(function () use ($resource) {
        $resource('employees', EmployeeController::class, 'employee', 'employee');
        $resource('departments', DepartmentController::class, 'department', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
        $resource('positions', PositionController::class, 'position', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
        $resource('leave-types', LeaveTypeController::class, 'leave', 'record', ['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::get('attendances', [AttendanceController::class, 'index'])
            ->name('attendances.index')->middleware('permission:attendance.view');
        Route::get('attendances/recap', [AttendanceController::class, 'recap'])
            ->name('attendances.recap')->middleware('permission:attendance.view');
        Route::get('attendances/create', [AttendanceController::class, 'create'])
            ->name('attendances.create')->middleware('permission:attendance.create');
        Route::post('attendances', [AttendanceController::class, 'store'])
            ->name('attendances.store')->middleware('permission:attendance.create');
        Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])
            ->name('attendances.destroy')->middleware('permission:attendance.delete');

        $resource('leaves', LeaveController::class, 'leave', 'leave');
        Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])
            ->name('leaves.approve')->middleware('permission:leave.approve');
        Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])
            ->name('leaves.reject')->middleware('permission:leave.approve');

        $resource('payrolls', PayrollController::class, 'payroll', 'payroll', ['index', 'create', 'store', 'show', 'destroy']);
        Route::put('payrolls/{payroll}/items', [PayrollController::class, 'updateItems'])
            ->name('payrolls.items')->middleware('permission:payroll.edit');
        Route::post('payrolls/{payroll}/regenerate', [PayrollController::class, 'regenerate'])
            ->name('payrolls.regenerate')->middleware('permission:payroll.edit');
        Route::post('payrolls/{payroll}/approve', [PayrollController::class, 'approve'])
            ->name('payrolls.approve')->middleware('permission:payroll.approve');
        Route::post('payrolls/{payroll}/pay', [PayrollController::class, 'pay'])
            ->name('payrolls.pay')->middleware('permission:payroll.approve');
        Route::post('payrolls/{payroll}/cancel', [PayrollController::class, 'cancel'])
            ->name('payrolls.cancel')->middleware('permission:payroll.approve');
        Route::get('payrolls/{payroll}/slip/{itemId}', [PayrollController::class, 'slip'])
            ->name('payrolls.slip')->middleware('permission:payroll.view');
    });

    /* --------------------------------------------------------------- Laporan */
    Route::middleware(['module:reports', 'permission:report.view'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('purchasing', [ReportController::class, 'purchasing'])->name('purchasing');
        Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('receivable-aging', [ReportController::class, 'receivableAging'])->name('receivable-aging');
        Route::get('payable-aging', [ReportController::class, 'payableAging'])->name('payable-aging');
        Route::get('top-products', [ReportController::class, 'topProducts'])->name('top-products');
    });

    /* ------------------------------------------------------------- Pengaturan */
    $resource('users', UserController::class, 'user', 'user', ['index', 'create', 'store', 'edit', 'update', 'destroy']);
    $resource('roles', RoleController::class, 'role', 'role', ['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit')->middleware('permission:setting.view');
    Route::middleware('permission:setting.edit')->group(function () {
        Route::put('settings/modules', [SettingController::class, 'updateModules'])->name('settings.modules');
        Route::put('settings/company', [SettingController::class, 'updateCompany'])->name('settings.company');
        Route::put('settings/accounting', [SettingController::class, 'updateAccounting'])->name('settings.accounting');
        Route::put('settings/operations', [SettingController::class, 'updateOperations'])->name('settings.operations');
        Route::put('settings/sequences/{sequence}', [SettingController::class, 'updateSequence'])->name('settings.sequence');
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->name('activity-logs.index')->middleware('permission:activity-log.view');
    Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])
        ->name('activity-logs.show')->middleware('permission:activity-log.view');
});
