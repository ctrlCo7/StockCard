<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\IssuanceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationEmailController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockCardController;
use App\Http\Controllers\StockReceiptController;
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/trend', [DashboardController::class, 'trend'])->name('dashboard.trend');
    Route::get('/search', SearchController::class)->name('search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/signature', [ProfileController::class, 'updateSignature'])->middleware('throttle:postings')->name('profile.signature');
    Route::delete('/profile/signature', [ProfileController::class, 'destroySignature'])->name('profile.signature.destroy');

    // Inventory items
    Route::middleware('can:items.view')->group(function () {
        Route::get('/items', [InventoryItemController::class, 'index'])->name('items.index');
        Route::get('/items/lookup', [InventoryItemController::class, 'lookup'])->name('items.lookup');
    });
    Route::middleware('can:items.manage')->group(function () {
        Route::get('/items/create', [InventoryItemController::class, 'create'])->name('items.create');
        Route::post('/items', [InventoryItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit', [InventoryItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [InventoryItemController::class, 'update'])->name('items.update');
        Route::patch('/items/{item}/archive', [InventoryItemController::class, 'archive'])->name('items.archive');
        Route::patch('/items/{item}/restore', [InventoryItemController::class, 'restore'])->name('items.restore');
    });
    Route::get('/items/{item}', [InventoryItemController::class, 'show'])->middleware('can:items.view')->name('items.show');

    // Stock cards & ledger
    Route::middleware('can:stockcards.view')->group(function () {
        Route::get('/stock-cards', [StockCardController::class, 'index'])->name('stock-cards.index');
        Route::get('/stock-cards/{item}', [StockCardController::class, 'show'])->name('stock-cards.show');
        Route::get('/stock-cards/{item}/print', [StockCardController::class, 'print'])->name('stock-cards.print');
        Route::get('/stock-cards/{item}/pdf', [StockCardController::class, 'pdf'])->name('stock-cards.pdf');
    });
    Route::post('/transactions/{transaction}/reverse', [StockTransactionController::class, 'reverse'])
        ->middleware(['can:stock.reverse', 'throttle:postings'])->name('transactions.reverse');

    // Stock receipts
    Route::middleware('can:stock.receive')->group(function () {
        Route::get('/receipts', [StockReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/receipts/create', [StockReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/receipts', [StockReceiptController::class, 'store'])->middleware('throttle:postings')->name('receipts.store');
        Route::get('/receipts/{receipt}', [StockReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/receipts/{receipt}/attachment', [StockReceiptController::class, 'attachment'])->name('receipts.attachment');
    });

    // Stock adjustments
    Route::middleware('can:stock.adjust')->group(function () {
        Route::get('/adjustments', [StockAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('/adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [StockAdjustmentController::class, 'store'])->middleware('throttle:postings')->name('adjustments.store');
        Route::get('/adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->name('adjustments.show');
    });

    // Requisition and Issue Slips (record-level rules in RequisitionPolicy)
    Route::prefix('requisitions')->name('requisitions.')->controller(RequisitionController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->middleware('can:requisitions.create')->name('create');
        Route::post('/', 'store')->middleware('can:requisitions.create')->name('store');
        Route::get('/{requisition}', 'show')->name('show');
        Route::get('/{requisition}/edit', 'edit')->name('edit');
        Route::put('/{requisition}', 'update')->name('update');
        Route::post('/{requisition}/submit', 'submit')->name('submit');
        Route::post('/{requisition}/forward', 'forward')->name('forward');
        Route::post('/{requisition}/approve', 'approve')->name('approve');
        Route::post('/{requisition}/reject', 'reject')->name('reject');
        Route::post('/{requisition}/return', 'returnForCorrection')->name('return');
        Route::post('/{requisition}/cancel', 'cancel')->name('cancel');
        Route::get('/{requisition}/issue', 'issueForm')->name('issue');
        Route::post('/{requisition}/issue', 'issue')->middleware('throttle:postings')->name('issue.store');
        Route::post('/{requisition}/acknowledge', 'acknowledge')->name('acknowledge');
        Route::get('/{requisition}/print', 'print')->name('print');
        Route::get('/{requisition}/pdf', 'pdf')->name('pdf');
    });

    Route::middleware('can:issuances.view')->group(function () {
        Route::get('/issuances', [IssuanceController::class, 'index'])->name('issuances.index');
        Route::get('/issuances/{issuance}', [IssuanceController::class, 'show'])->name('issuances.show');
    });

    // In-app messages (participants only; see ConversationPolicy)
    Route::prefix('messages')->name('messages.')->controller(MessageController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/new', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:messages')->name('store');
        Route::get('/{conversation}', 'show')->name('show');
        Route::post('/{conversation}/reply', 'reply')->middleware('throttle:messages')->name('reply');
    });

    // "Send email" buttons (authorized in SendNotificationEmailRequest)
    Route::middleware('throttle:emails')->controller(NotificationEmailController::class)->group(function () {
        Route::post('/requisitions/{requisition}/email', 'requisition')->name('requisitions.email');
        Route::post('/notifications/low-stock', 'lowStock')->name('notifications.low-stock');
    });

    // Reports
    Route::middleware('can:reports.view')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    });

    // Administration
    Route::middleware('can:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    });
    Route::middleware('can:audit.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    });
    Route::middleware('can:masterdata.manage')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show', 'create', 'edit']);
        Route::resource('offices', OfficeController::class)->except(['show', 'create', 'edit']);
    });
});
