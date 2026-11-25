<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliverySettingsController;
use App\Http\Controllers\Mitra\OrderController as MitraOrderController;
use App\Http\Controllers\Mitra\PortalController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Admin & kasir
    Route::middleware('role:admin,kasir')->group(function () {
        Route::get('orders/quote', [OrderController::class, 'quote'])->name('orders.quote');
        Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::patch('orders/{order}/pay', [OrderController::class, 'pay'])->name('orders.pay');
        Route::get('orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
        Route::patch('orders/{order}/staff', [OrderController::class, 'assignStaff'])->name('orders.staff');
        Route::patch('orders/{order}/items', [OrderController::class, 'fillItems'])->name('orders.items');

        Route::resource('customers', CustomerController::class)->except(['destroy']);
        Route::post('customers/{customer}/redeem', [CustomerController::class, 'redeem'])->name('customers.redeem');

        Route::resource('deliveries', DeliveryController::class)->only(['index', 'create', 'store']);
        Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('deliveries.status');
    });

    // Admin saja
    Route::middleware('role:admin')->group(function () {
        Route::resource('services', ServiceController::class)->except(['show', 'destroy']);
        Route::resource('mitras', MitraController::class)->except(['destroy']);
        Route::post('mitras/{mitra}/payout', [MitraController::class, 'payout'])->name('mitras.payout');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::put('staff/rates', [StaffController::class, 'updateRates'])->name('staff.rates');
        Route::resource('staff', StaffController::class)->except(['destroy'])->parameters(['staff' => 'staff']);
        Route::post('staff/{staff}/payout', [StaffController::class, 'payout'])->name('staff.payout');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('settings/delivery', [DeliverySettingsController::class, 'edit'])->name('settings.delivery');
        Route::put('settings/delivery', [DeliverySettingsController::class, 'update'])->name('settings.delivery.update');
    });

    // Mitra dropbox
    Route::middleware('role:mitra')->prefix('mitra')->name('mitra.')->group(function () {
        Route::get('/', PortalController::class)->name('portal');
        Route::get('orders/create', [MitraOrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [MitraOrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}/receipt', [MitraOrderController::class, 'receipt'])->name('orders.receipt');
    });
});
