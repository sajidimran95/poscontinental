<?php

use App\Http\Controllers\CustomerApp\CustomerAuthController;
use App\Http\Controllers\CustomerApp\CustomerPortalController;
use App\Http\Controllers\CustomerApp\CustomerPwaController;
use App\Http\Controllers\DeliveryApp\DeliveryAppController;
use App\Http\Controllers\DeliveryApp\DeliveryAuthController;
use App\Http\Controllers\DeliveryApp\DeliveryPwaController;
use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\DocumentTabController;
use App\Http\Controllers\MsaSalesFileController;
use App\Http\Controllers\ItemMediaController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\Sale\SaleAuthController;
use App\Http\Controllers\Sale\SaleChatController;
use App\Http\Controllers\Sale\SaleParkedSaleController;
use App\Http\Controllers\Sale\SalePortalController;
use App\Http\Controllers\Sale\SalePwaController;
use App\Http\Controllers\SalesOrderWindowController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/login');

Route::post('logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

// Public item media (no auth) — <img> tags must load without a session cookie.
Route::get('media/{path}', [PublicMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::middleware(['auth', 'feature'])->group(function () {
    Route::view('home', 'home')->name('home');
    Route::redirect('dashboard', '/home')->name('dashboard');

    Route::get('pos/tabs/open', [DocumentTabController::class, 'open'])->name('pos.tabs.open');
    Route::post('pos/tabs/close-all', [DocumentTabController::class, 'closeAll'])->name('pos.tabs.close-all');
    Route::post('pos/tabs/{tab}/close', [DocumentTabController::class, 'close'])
        ->where('tab', '[0-9a-fA-F\-]{36}')
        ->name('pos.tabs.close');

    Volt::route('team-chat', 'pages.team-chat.index')->name('team-chat.index');

    Volt::route('profile', 'pages.profile')->name('profile');

    // Lookups
    Volt::route('lookups', 'pages.lookups.index')->name('lookups.index');

    // Admin (company POS)
    Volt::route('admin/company-settings', 'pages.tobacco.company-settings')->name('admin.company-settings');
    Volt::route('admin/overselling-settings', 'pages.admin.overselling-settings')->name('admin.overselling-settings');
    Volt::route('admin/japsai', 'pages.admin.japsai')->name('admin.japsai');
    Route::redirect('tobacco/filing-settings', '/admin/company-settings');
    Volt::route('admin/users', 'pages.admin.users')->name('admin.users.index');
    Volt::route('admin/email-setup', 'pages.admin.email-setup')->name('admin.email-setup');
    Volt::route('admin/email-logs', 'pages.admin.email-logs')->name('admin.email-logs');
    Volt::route('admin/terminal', 'pages.admin.terminal')->name('admin.terminal');

    // Purchasing / Suppliers
    Volt::route('purchasing/suppliers', 'pages.purchasing.suppliers.index')->name('purchasing.suppliers.index');
    Volt::route('purchasing/suppliers/create', 'pages.purchasing.suppliers.form')->name('purchasing.suppliers.create');
    Volt::route('purchasing/suppliers/{supplier}/edit', 'pages.purchasing.suppliers.form')->name('purchasing.suppliers.edit');

    Volt::route('purchasing/orders', 'pages.purchasing.orders.index')->name('purchasing.orders.index');
    Volt::route('purchasing/orders/create', 'pages.purchasing.orders.form')->name('purchasing.orders.create');
    Route::get('purchasing/orders/{purchaseOrder}/print', [DocumentPdfController::class, 'purchaseOrder'])
        ->name('purchasing.orders.print');
    Volt::route('purchasing/orders/{purchaseOrder}/edit', 'pages.purchasing.orders.form')->name('purchasing.orders.edit');
    Volt::route('purchasing/orders/{purchaseOrder}', 'pages.purchasing.orders.form')->name('purchasing.orders.show');
    Volt::route('purchasing/receivings', 'pages.purchasing.receivings.index')->name('purchasing.receivings.index');
    Route::get('purchasing/receivings/{receiving}/print', [DocumentPdfController::class, 'receiving'])
        ->name('purchasing.receivings.print');
    Volt::route('purchasing/receivings/{receiving}/edit', 'pages.purchasing.receivings.form')->name('purchasing.receivings.edit');
    Volt::route('purchasing/receivings/{receiving}', 'pages.purchasing.receivings.form')->name('purchasing.receivings.show');
    Volt::route('purchasing/rtv', 'pages.purchasing.rtv.index')->name('purchasing.rtv.index');
    Route::get('purchasing/rtv/{rtv}/print', [DocumentPdfController::class, 'rtv'])
        ->name('purchasing.rtv.print');

    // Inventory / Items
    Volt::route('inventory/items', 'pages.inventory.items.index')->name('inventory.items.index');
    Volt::route('inventory/items/create', 'pages.inventory.items.form')->name('inventory.items.create');
    Route::get('inventory/items/print', [DocumentPdfController::class, 'itemsList'])
        ->name('inventory.items.print');
    Route::post('inventory/items/media', [ItemMediaController::class, 'store'])->name('inventory.items.media');
    Volt::route('inventory/items/{item}/edit', 'pages.inventory.items.form')->name('inventory.items.edit');
    Volt::route('inventory/items/{item}', 'pages.inventory.items.form')->name('inventory.items.show');
    Volt::route('inventory/stock-counts', 'pages.inventory.stock-counts.index')->name('inventory.stock-counts.index');
    Volt::route('inventory/stock-counts/create', 'pages.inventory.stock-counts.form')->name('inventory.stock-counts.create');
    Volt::route('inventory/stock-counts/{stockCount}/edit', 'pages.inventory.stock-counts.form')->name('inventory.stock-counts.edit');
    Volt::route('inventory/bulk-pricing', 'pages.inventory.bulk-pricing')->name('inventory.bulk-pricing');

    // Sales
    Volt::route('sales/customers', 'pages.sales.customers.index')->name('sales.customers.index');
    Volt::route('sales/customers/create', 'pages.sales.customers.form')->name('sales.customers.create');
    Route::get('sales/customers/print', [DocumentPdfController::class, 'customersList'])
        ->name('sales.customers.print');
    Volt::route('sales/customers/{customer}/edit', 'pages.sales.customers.form')->name('sales.customers.edit');
    Volt::route('sales/customers/{customer}', 'pages.sales.customers.form')->name('sales.customers.show');
    Volt::route('sales/orders', 'pages.sales.orders.index')->name('sales.orders.index');
    Route::post('sales/orders/create/windows', [SalesOrderWindowController::class, 'open'])
        ->name('sales.orders.windows.open');
    Route::post('sales/orders/create/windows/{window}/close', [SalesOrderWindowController::class, 'close'])
        ->where('window', '[0-9a-fA-F\-]{36}')
        ->name('sales.orders.windows.close');
    Volt::route('sales/orders/create', 'pages.sales.orders.form')->name('sales.orders.create');
    Route::get('sales/orders/{salesOrder}/print', [DocumentPdfController::class, 'salesOrder'])
        ->name('sales.orders.print');
    Route::get('sales/orders/{salesOrder}/invoice', [DocumentPdfController::class, 'salesOrderInvoice'])
        ->name('sales.orders.invoice');
    Route::get('sales/orders/{salesOrder}/pick-list', [DocumentPdfController::class, 'salesOrderPickList'])
        ->name('sales.orders.pick-list');
    Volt::route('sales/orders/{salesOrder}/edit', 'pages.sales.orders.form')->name('sales.orders.edit');
    Volt::route('sales/orders/{salesOrder}', 'pages.sales.orders.form')->name('sales.orders.show');
    Volt::route('sales/invoices', 'pages.sales.invoices.index')->name('sales.invoices.index');
    Route::get('sales/invoices/{invoice}/pdf', [DocumentPdfController::class, 'invoice'])
        ->name('sales.invoices.pdf');
    Route::get('sales/invoices/{invoice}/pick-list', [DocumentPdfController::class, 'invoicePickList'])
        ->name('sales.invoices.pick-list');
    Route::post('sales/invoices/{invoice}/email', [DocumentPdfController::class, 'emailInvoice'])
        ->name('sales.invoices.email');
    Route::get('sales/invoices/{invoice}/payments/{payment}/receipt', PaymentReceiptController::class)
        ->name('sales.invoices.receipt');
    Volt::route('sales/payments', 'pages.sales.payments.index')->name('sales.payments.index');
    Volt::route('sales/credit-memos', 'pages.sales.credit-memos.index')->name('sales.credit-memos.index');
    Route::get('sales/credit-memos/{memo}/pdf', [DocumentPdfController::class, 'creditMemo'])
        ->name('sales.credit-memos.pdf');
    Route::post('sales/credit-memos/{memo}/email', [DocumentPdfController::class, 'emailCreditMemo'])
        ->name('sales.credit-memos.email');

    // Stamp inventory (Inventory menu) + MSA report (Reports menu)
    Volt::route('inventory/stamp-inventory', 'pages.tobacco.stamp-inventory')->name('inventory.stamp-inventory');
    Route::redirect('tobacco/stamp-inventory', '/inventory/stamp-inventory');
    Route::redirect('tobacco/filing', '/reports/msa');

    // Inquiries & Reports
    Volt::route('inquiries/stock-status', 'pages.inquiries.stock-status')->name('inquiries.stock-status');
    Volt::route('inquiries/item-velocity', 'pages.inquiries.item-velocity')->name('inquiries.item-velocity');

    // Reports catalog (Chief-style Report Criteria + print layout)
    Volt::route('reports/sales-by-customer', 'pages.reports.sales-by-customer')->name('reports.sales-by-customer');
    Volt::route('reports/sales-by-item', 'pages.reports.sales-by-item')->name('reports.sales-by-item');
    Volt::route('reports/sales-by-categories', 'pages.reports.sales-by-categories')->name('reports.sales-by-categories');
    Volt::route('reports/sales-by-totals', 'pages.reports.sales-by-totals')->name('reports.sales-by-totals');
    Volt::route('reports/sales-by-stick-count', 'pages.reports.sales-by-stick-count')->name('reports.sales-by-stick-count');
    Volt::route('reports/sales-by-manufacturer', 'pages.reports.sales-by-manufacturer')->name('reports.sales-by-manufacturer');
    Volt::route('reports/purchases-by-stick-count', 'pages.reports.purchases-by-stick-count')->name('reports.purchases-by-stick-count');
    Volt::route('reports/purchases-by-item', 'pages.reports.purchases-by-item')->name('reports.purchases-by-item');
    Route::redirect('reports/sales', '/reports/sales-by-customer')->name('reports.sales');

    Volt::route('reports/price-list', 'pages.reports.price-list')->name('reports.price-list');
    Volt::route('reports/msa', 'pages.tobacco.filing')->name('reports.msa');
    Route::get('reports/msa/file', MsaSalesFileController::class)->name('reports.msa.file');
    Route::get('reports/price-list/print', [DocumentPdfController::class, 'priceList'])
        ->name('reports.price-list.print');

    // Delivery management (add-on; does not replace /sale/delivery)
    Volt::route('deliveries', 'pages.delivery.driver')->name('deliveries.driver');
    Volt::route('deliveries/assign', 'pages.delivery.assign')->name('deliveries.assign');
    Volt::route('deliveries/routes', 'pages.delivery.routes')->name('deliveries.routes');
    Volt::route('deliveries/routes/{deliveryRoute}', 'pages.delivery.route-show')->name('deliveries.routes.show');
    Volt::route('deliveries/history', 'pages.delivery.history')->name('deliveries.history');
    Volt::route('deliveries/men', 'pages.delivery.men')->name('deliveries.men');
    Route::redirect('deliveries/locations', '/admin/company-settings')->name('deliveries.locations');
    Volt::route('deliveries/areas', 'pages.delivery.areas')->name('deliveries.areas');
});

require __DIR__.'/auth.php';

Route::prefix('customer')->name('customer.')->group(function () {
    Route::get('/pwa/manifest.webmanifest', [CustomerPwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/pwa/sw.js', [CustomerPwaController::class, 'serviceWorker'])->name('pwa.sw');
    Route::get('/pwa/offline', [CustomerPwaController::class, 'offline'])->name('pwa.offline');

    Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout')->middleware('auth:customer');

    Route::middleware(['auth:customer', 'customer.app'])->group(function () {
        Route::get('/', [CustomerPortalController::class, 'home'])->name('home');
        Route::get('/account', [CustomerPortalController::class, 'account'])->name('account');
        Route::get('/profile', [CustomerPortalController::class, 'profile'])->name('profile');
        Route::post('/profile/password', [CustomerPortalController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/location', [CustomerPortalController::class, 'updateLocation'])->name('profile.location');
        Route::get('/documents', [CustomerPortalController::class, 'documents'])->name('documents');
        Route::get('/documents/{invoice}/invoice', [CustomerPortalController::class, 'showInvoice'])->name('documents.invoice');
        Route::get('/price-check', [CustomerPortalController::class, 'priceCheck'])->name('price');
        Route::get('/orders', [CustomerPortalController::class, 'orders'])->name('orders');
        Route::get('/orders/create', [CustomerPortalController::class, 'create'])->name('orders.create');
        Route::post('/orders', [CustomerPortalController::class, 'store'])->name('orders.store');
        Route::get('/orders/{salesOrder}/invoice', [CustomerPortalController::class, 'downloadInvoice'])->name('orders.invoice');
        Route::get('/orders/{salesOrder}', [CustomerPortalController::class, 'show'])->name('orders.show');
        Route::get('/api/customers', [CustomerPortalController::class, 'searchCustomers'])->name('api.customers');
        Route::get('/api/customers/{customer}/shipping', [CustomerPortalController::class, 'customerShipping'])->name('api.customer_shipping');
        Route::get('/api/products', [CustomerPortalController::class, 'searchProducts'])->name('api.products');
        Route::get('/api/categories', [CustomerPortalController::class, 'categoriesTree'])->name('api.categories');
        Route::get('/api/parked-sales', [CustomerPortalController::class, 'parkedSales'])->name('api.parked_sales');
    });
});

Route::prefix('sale')->name('sale.')->group(function () {
    Route::get('/pwa/manifest.webmanifest', [SalePwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/pwa/sw.js', [SalePwaController::class, 'serviceWorker'])->name('pwa.sw');
    Route::get('/pwa/offline', [SalePwaController::class, 'offline'])->name('pwa.offline');

    Route::get('/login', [SaleAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [SaleAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [SaleAuthController::class, 'logout'])->name('logout')->middleware('auth:sale');

    Route::middleware(['auth:sale', 'sale.app'])->group(function () {
        Route::get('/', [SalePortalController::class, 'home'])->name('home');
        Route::get('/orders', [SalePortalController::class, 'orders'])->name('orders');
        Route::get('/orders/create', [SalePortalController::class, 'create'])->name('orders.create');
        Route::post('/orders', [SalePortalController::class, 'store'])->name('orders.store');
        Route::get('/orders/{salesOrder}/edit', [SalePortalController::class, 'edit'])->name('orders.edit');
        Route::put('/orders/{salesOrder}', [SalePortalController::class, 'update'])->name('orders.update');
        Route::get('/orders/{salesOrder}/invoice', [SalePortalController::class, 'downloadInvoice'])->name('orders.invoice');
        Route::delete('/orders/{salesOrder}', [SalePortalController::class, 'destroy'])->name('orders.destroy');
        Route::get('/orders/{salesOrder}', [SalePortalController::class, 'show'])->name('orders.show');
        Route::get('/account', [SalePortalController::class, 'account'])->name('account');
        Route::post('/account/location', [SalePortalController::class, 'updateLocation'])->name('account.location');
        Route::post('/account/password', [SalePortalController::class, 'updatePassword'])->name('account.password');
        Route::post('/chat/dm', [SaleChatController::class, 'dm'])->name('chat.dm');
        Route::post('/chat/{channel}/messages', [SaleChatController::class, 'send'])->name('chat.send')->whereNumber('channel');
        Route::get('/chat/{channel}/poll', [SaleChatController::class, 'poll'])->name('chat.poll')->whereNumber('channel');
        Route::get('/chat/{channel}/older', [SaleChatController::class, 'older'])->name('chat.older')->whereNumber('channel');
        Route::get('/chat/{channel?}', [SaleChatController::class, 'index'])->name('chat')->whereNumber('channel');
        Route::get('/delivery', [SalePortalController::class, 'delivery'])->name('delivery');
        Route::get('/products', [SalePortalController::class, 'products'])->name('products');
        Route::get('/customers', [SalePortalController::class, 'customers'])->name('customers');
        Route::get('/customers/create', [SalePortalController::class, 'createCustomer'])->name('customers.create');
        Route::post('/customers', [SalePortalController::class, 'storeCustomer'])->name('customers.store');
        Route::get('/api/customers', [SalePortalController::class, 'searchCustomers'])->name('api.customers');
        Route::get('/api/customers/{customer}/shipping', [SalePortalController::class, 'customerShipping'])->name('api.customer_shipping');
        Route::get('/api/products', [SalePortalController::class, 'searchProducts'])->name('api.products');
        Route::get('/api/parked-sales', [SaleParkedSaleController::class, 'index'])->name('api.parked_sales');
        Route::post('/api/parked-sales', [SaleParkedSaleController::class, 'store'])->name('api.parked_sales.store');
        Route::get('/api/parked-sales/{parkedSale}', [SaleParkedSaleController::class, 'show'])->name('api.parked_sales.show');
        Route::delete('/api/parked-sales/{parkedSale}', [SaleParkedSaleController::class, 'destroy'])->name('api.parked_sales.destroy');
        Route::get('/api/categories', [SalePortalController::class, 'categoriesTree'])->name('api.categories');
        Route::get('/api/last-purchases', [SalePortalController::class, 'lastPurchases'])->name('api.last_purchases');
        Route::get('/api/items', [SalePortalController::class, 'searchItems'])->name('api.items');
    });
});

Route::prefix('delivery')->name('delivery.app.')->group(function () {
    Route::get('/pwa/manifest.webmanifest', [DeliveryPwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/pwa/sw.js', [DeliveryPwaController::class, 'serviceWorker'])->name('pwa.sw');
    Route::get('/pwa/offline', [DeliveryPwaController::class, 'offline'])->name('pwa.offline');

    Route::get('/login', [DeliveryAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [DeliveryAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [DeliveryAuthController::class, 'logout'])->name('logout')->middleware('auth:delivery');

    Route::middleware(['auth:delivery', 'delivery.app'])->group(function () {
        Route::get('/', [DeliveryAppController::class, 'home'])->name('home');
        Route::get('/route', [DeliveryAppController::class, 'route'])->name('route');
        Route::get('/assigned', [DeliveryAppController::class, 'assigned'])->name('assigned');
        Route::get('/all', [DeliveryAppController::class, 'all'])->name('all');
        Route::get('/delivered', [DeliveryAppController::class, 'history'])->name('history');
        Route::post('/start', [DeliveryAppController::class, 'start'])->name('start');
        Route::post('/stops/{stop}/arrived', [DeliveryAppController::class, 'arrived'])->name('arrived')->whereNumber('stop');
        Route::post('/stops/{stop}/delivered', [DeliveryAppController::class, 'delivered'])->name('delivered')->whereNumber('stop');
        Route::post('/stops/{stop}/failed', [DeliveryAppController::class, 'failed'])->name('failed')->whereNumber('stop');
        Route::post('/stops/{stop}/notes', [DeliveryAppController::class, 'notes'])->name('notes')->whereNumber('stop');
    });
});
