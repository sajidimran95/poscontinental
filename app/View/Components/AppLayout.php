<?php

namespace App\View\Components;

use App\Models\Item;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function render(): View
    {
        $companyId = auth()->user()?->company_id;
        $lowStockCount = 0;

        if ($companyId) {
            $lowStockCount = Item::query()
                ->where('company_id', $companyId)
                ->lowStock()
                ->count();
        }

        $routeName = Route::currentRouteName() ?? 'home';
        $label = $this->tabLabel($routeName);

        $tabs = [
            ['label' => 'Home', 'route' => 'home', 'url' => route('home')],
        ];

        if ($routeName !== 'home') {
            $tabs[] = [
                'label' => $label,
                'route' => $routeName,
                'url' => url()->current(),
            ];
        }

        return view('layouts.app', [
            'lowStockCount' => $lowStockCount,
            'documentTabs' => $tabs,
            'activeTabRoute' => $routeName,
            'pageTitle' => $label,
        ]);
    }

    protected function tabLabel(string $routeName): string
    {
        return match (true) {
            $routeName === 'home' => 'Home',
            str_contains($routeName, 'suppliers.create') => 'New Supplier',
            str_contains($routeName, 'suppliers.edit') => 'Edit Supplier',
            str_contains($routeName, 'suppliers') => 'Suppliers',
            str_contains($routeName, 'items.create') => 'New Item',
            str_contains($routeName, 'items.edit') => 'Edit Item',
            str_contains($routeName, 'items') => 'Items',
            str_contains($routeName, 'customers.create') => 'New Customer',
            str_contains($routeName, 'customers.edit') => 'Edit Customer',
            str_contains($routeName, 'customers') => 'Customers',
            str_contains($routeName, 'orders.create') && str_contains($routeName, 'sales') => 'New Sales Order',
            str_contains($routeName, 'orders') && str_contains($routeName, 'sales') => 'Orders',
            str_contains($routeName, 'orders.create') && str_contains($routeName, 'purchasing') => 'New Purchase Order',
            str_contains($routeName, 'orders') && str_contains($routeName, 'purchasing') => 'Purchase Orders',
            str_contains($routeName, 'invoices') => 'Invoices',
            str_contains($routeName, 'payments') => 'Payments',
            str_contains($routeName, 'credit-memos') => 'Credit Memos',
            str_contains($routeName, 'stock-counts') => 'Stock Counts',
            str_contains($routeName, 'receivings') => 'Inventory Receivings',
            str_contains($routeName, 'rtv') => 'RTVs',
            str_contains($routeName, 'stock-status') => 'Stock Status',
            str_contains($routeName, 'item-velocity') => 'Item Velocity',
            str_contains($routeName, 'lookups') => 'Lookups',
            str_contains($routeName, 'reports') => 'Reports',
            str_contains($routeName, 'bulk-pricing') => 'Bulk Pricing',
            default => str($routeName)->afterLast('.')->headline()->toString(),
        };
    }
}
