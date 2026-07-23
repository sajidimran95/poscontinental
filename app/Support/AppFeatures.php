<?php

namespace App\Support;

/**
 * Feature keys used for role permissions (menus + route gating).
 *
 * @return array<string, array{label: string, group: string, routes: list<string>}>
 */
class AppFeatures
{
    public static function all(): array
    {
        return [
            'admin.users' => [
                'label' => 'Users & Roles',
                'group' => 'Admin',
                'routes' => ['admin.users.index'],
            ],
            'admin.email' => [
                'label' => 'Email Setup & Logs',
                'group' => 'Admin',
                'routes' => ['admin.email-setup', 'admin.email-logs'],
            ],
            'admin.terminal' => [
                'label' => 'Terminal',
                'group' => 'Admin',
                'routes' => ['admin.terminal'],
            ],
            'lookups' => [
                'label' => 'Lookups',
                'group' => 'Admin',
                'routes' => ['lookups.index'],
            ],
            'sales.orders' => [
                'label' => 'Sales Orders',
                'group' => 'Sales',
                'routes' => ['sales.orders.index', 'sales.orders.create', 'sales.orders.edit', 'sales.orders.show', 'sales.orders.print'],
            ],
            'sales.customers' => [
                'label' => 'Customers',
                'group' => 'Sales',
                'routes' => ['sales.customers.index', 'sales.customers.create', 'sales.customers.edit'],
            ],
            'sales.invoices' => [
                'label' => 'Invoices',
                'group' => 'Sales',
                'routes' => ['sales.invoices.index', 'sales.invoices.pdf', 'sales.invoices.email', 'sales.invoices.receipt'],
            ],
            'sales.payments' => [
                'label' => 'Payments',
                'group' => 'Sales',
                'routes' => ['sales.payments.index'],
            ],
            'sales.credit_memos' => [
                'label' => 'Credit Memos',
                'group' => 'Sales',
                'routes' => ['sales.credit-memos.index', 'sales.credit-memos.pdf', 'sales.credit-memos.email'],
            ],
            'inventory.items' => [
                'label' => 'Items',
                'group' => 'Inventory',
                'routes' => ['inventory.items.index', 'inventory.items.create', 'inventory.items.edit', 'inventory.items.show', 'inventory.items.media'],
            ],
            'inventory.stock_counts' => [
                'label' => 'Stock Counts',
                'group' => 'Inventory',
                'routes' => ['inventory.stock-counts.index', 'inventory.stock-counts.create', 'inventory.stock-counts.edit'],
            ],
            'inventory.bulk_pricing' => [
                'label' => 'Bulk Pricing',
                'group' => 'Inventory',
                'routes' => ['inventory.bulk-pricing'],
            ],
            'purchasing.orders' => [
                'label' => 'Purchase Orders',
                'group' => 'Purchasing',
                'routes' => ['purchasing.orders.index', 'purchasing.orders.create', 'purchasing.orders.edit', 'purchasing.orders.show', 'purchasing.orders.print'],
            ],
            'purchasing.receivings' => [
                'label' => 'Receivings',
                'group' => 'Purchasing',
                'routes' => ['purchasing.receivings.index', 'purchasing.receivings.edit', 'purchasing.receivings.show', 'purchasing.receivings.print'],
            ],
            'purchasing.rtv' => [
                'label' => 'Return to Vendor',
                'group' => 'Purchasing',
                'routes' => ['purchasing.rtv.index', 'purchasing.rtv.print'],
            ],
            'purchasing.suppliers' => [
                'label' => 'Suppliers',
                'group' => 'Purchasing',
                'routes' => ['purchasing.suppliers.index', 'purchasing.suppliers.create', 'purchasing.suppliers.edit'],
            ],
            'inquiries' => [
                'label' => 'Inquiries',
                'group' => 'Inquiries',
                'routes' => ['inquiries.stock-status', 'inquiries.item-velocity'],
            ],
            'reports' => [
                'label' => 'Reports',
                'group' => 'Reports',
                'routes' => ['reports.sales', 'reports.price-list'],
            ],
            'tobacco' => [
                'label' => 'Tobacco Filing',
                'group' => 'Tobacco',
                'routes' => ['tobacco.stamp-inventory', 'tobacco.filing'],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function featureForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        foreach (self::all() as $key => $meta) {
            foreach ($meta['routes'] as $route) {
                if ($route === $routeName || str_starts_with($routeName, rtrim($route, '.'))) {
                    // exact match preferred
                }
            }
            if (in_array($routeName, $meta['routes'], true)) {
                return $key;
            }
        }

        // Prefix fallback: sales.orders.foo → sales.orders
        foreach (self::all() as $key => $meta) {
            foreach ($meta['routes'] as $route) {
                $prefix = preg_replace('/\.(index|create|edit|show|print|pdf|email|receipt|media)$/', '', $route);
                if ($prefix && (str_starts_with($routeName, $prefix.'.') || $routeName === $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::all() as $key => $meta) {
            $grouped[$meta['group']][$key] = $meta['label'];
        }

        return $grouped;
    }
}
