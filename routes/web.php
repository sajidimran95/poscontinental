<?php

use App\Http\Controllers\LogoutController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/login');

Route::post('logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::view('home', 'home')->name('home');
    Route::redirect('dashboard', '/home')->name('dashboard');

    Route::view('profile', 'profile')->name('profile');

    // Lookups
    Volt::route('lookups', 'pages.lookups.index')->name('lookups.index');

    // Purchasing / Suppliers
    Volt::route('purchasing/suppliers', 'pages.purchasing.suppliers.index')->name('purchasing.suppliers.index');
    Volt::route('purchasing/suppliers/create', 'pages.purchasing.suppliers.form')->name('purchasing.suppliers.create');
    Volt::route('purchasing/suppliers/{supplier}/edit', 'pages.purchasing.suppliers.form')->name('purchasing.suppliers.edit');

    Volt::route('purchasing/orders', 'pages.purchasing.orders.index')->name('purchasing.orders.index');
    Volt::route('purchasing/orders/create', 'pages.purchasing.orders.form')->name('purchasing.orders.create');
    Volt::route('purchasing/orders/{purchaseOrder}/edit', 'pages.purchasing.orders.form')->name('purchasing.orders.edit');
    Volt::route('purchasing/receivings', 'pages.purchasing.receivings.index')->name('purchasing.receivings.index');
    Volt::route('purchasing/receivings/{receiving}/edit', 'pages.purchasing.receivings.form')->name('purchasing.receivings.edit');
    Volt::route('purchasing/rtv', 'pages.purchasing.rtv.index')->name('purchasing.rtv.index');

    // Inventory / Items
    Volt::route('inventory/items', 'pages.inventory.items.index')->name('inventory.items.index');
    Volt::route('inventory/items/create', 'pages.inventory.items.form')->name('inventory.items.create');
    Volt::route('inventory/items/{item}/edit', 'pages.inventory.items.form')->name('inventory.items.edit');
    Volt::route('inventory/stock-counts', 'pages.inventory.stock-counts.index')->name('inventory.stock-counts.index');
    Volt::route('inventory/stock-counts/create', 'pages.inventory.stock-counts.form')->name('inventory.stock-counts.create');
    Volt::route('inventory/stock-counts/{stockCount}/edit', 'pages.inventory.stock-counts.form')->name('inventory.stock-counts.edit');
    Volt::route('inventory/bulk-pricing', 'pages.modules.placeholder')->name('inventory.bulk-pricing');

    // Sales
    Volt::route('sales/customers', 'pages.sales.customers.index')->name('sales.customers.index');
    Volt::route('sales/customers/create', 'pages.sales.customers.form')->name('sales.customers.create');
    Volt::route('sales/customers/{customer}/edit', 'pages.sales.customers.form')->name('sales.customers.edit');
    Volt::route('sales/orders', 'pages.sales.orders.index')->name('sales.orders.index');
    Volt::route('sales/orders/create', 'pages.sales.orders.form')->name('sales.orders.create');
    Volt::route('sales/orders/{salesOrder}/edit', 'pages.sales.orders.form')->name('sales.orders.edit');
    Volt::route('sales/invoices', 'pages.sales.invoices.index')->name('sales.invoices.index');
    Volt::route('sales/payments', 'pages.sales.payments.index')->name('sales.payments.index');
    Volt::route('sales/credit-memos', 'pages.sales.credit-memos.index')->name('sales.credit-memos.index');

    // Inquiries & Reports
    Volt::route('inquiries/stock-status', 'pages.modules.placeholder')->name('inquiries.stock-status');
    Volt::route('inquiries/item-velocity', 'pages.modules.placeholder')->name('inquiries.item-velocity');
    Volt::route('reports/sales', 'pages.modules.placeholder')->name('reports.sales');
    Volt::route('reports/price-list', 'pages.modules.placeholder')->name('reports.price-list');
});

require __DIR__.'/auth.php';
