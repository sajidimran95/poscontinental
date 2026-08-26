<?php

namespace App\Support;

/**
 * Feature keys used for role permissions (menus + route gating).
 *
 * Permissions are stored as "feature.action" strings, e.g. "sales.orders.view".
 * Legacy bare feature keys (e.g. "sales.orders") mean view+edit+delete.
 *
 * @return array<string, array{label: string, group: string, routes: list<string>}>
 */
class AppFeatures
{
    public const ACTIONS = ['view', 'edit', 'delete'];

    /**
     * Legacy feature keys renamed/split — expand old grants on read.
     *
     * @var array<string, list<string>>
     */
    public const LEGACY_ALIASES = [
        'inquiries' => ['inquiries.stock_status', 'inquiries.item_velocity'],
        'reports' => [
            'reports.sales',
            'reports.purchases',
            'reports.price_list',
            'reports.msa',
        ],
        'admin.email' => ['admin.email_setup', 'admin.email_logs'],
    ];

    /** @var array<string, array{label: string, group: string, routes: list<string>}>|null */
    private static ?array $catalog = null;

    /** @var list<string>|null */
    private static ?array $keyList = null;

    /** @var array<string, string>|null */
    private static ?array $routeFeatureMap = null;

    /** @var array<string, array<string, list<string>>> */
    private static array $expandCache = [];

    public static function all(): array
    {
        return self::$catalog ??= [
            'admin.company' => [
                'label' => 'Company Settings',
                'group' => 'File',
                'routes' => ['admin.company-settings'],
            ],
            'admin.overselling' => [
                'label' => 'Overselling Settings',
                'group' => 'File',
                'routes' => ['admin.overselling-settings'],
            ],
            'admin.japsai' => [
                'label' => 'POS AI Settings',
                'group' => 'File',
                'routes' => ['admin.japsai'],
            ],
            'admin.japsai_chat' => [
                'label' => 'POS AI Chat',
                'group' => 'File',
                'routes' => [],
            ],
            'admin.users' => [
                'label' => 'Users & Roles',
                'group' => 'File',
                'routes' => ['admin.users.index'],
            ],
            'admin.email_setup' => [
                'label' => 'Email Setup',
                'group' => 'File',
                'routes' => ['admin.email-setup'],
            ],
            'admin.email_logs' => [
                'label' => 'Email Send Log',
                'group' => 'File',
                'routes' => ['admin.email-logs'],
            ],
            'inquiries.stock_status' => [
                'label' => 'Stock Status',
                'group' => 'Inquiry',
                'routes' => ['inquiries.stock-status'],
            ],
            'inquiries.item_velocity' => [
                'label' => 'Item Velocity',
                'group' => 'Inquiry',
                'routes' => ['inquiries.item-velocity'],
            ],
            'inventory.items' => [
                'label' => 'Items',
                'group' => 'Inventory',
                'routes' => ['inventory.items.index', 'inventory.items.create', 'inventory.items.edit', 'inventory.items.show', 'inventory.items.media'],
            ],
            'inventory.stock_counts' => [
                'label' => 'Stock Counts',
                'group' => 'Inventory',
                'routes' => [
                    'inventory.stock-counts.index',
                    'inventory.stock-counts.create',
                    'inventory.stock-counts.edit',
                ],
            ],
            'inventory.bulk_pricing' => [
                'label' => 'Bulk Pricing',
                'group' => 'Inventory',
                'routes' => ['inventory.bulk-pricing'],
            ],
            'inventory.stamp_inventory' => [
                'label' => 'Stamp Inventory',
                'group' => 'Inventory',
                'routes' => ['tobacco.stamp-inventory', 'inventory.stamp-inventory'],
            ],
            'sales.orders' => [
                'label' => 'Sales Orders',
                'group' => 'Sales',
                'routes' => ['sales.orders.index', 'sales.orders.create', 'sales.orders.edit', 'sales.orders.show', 'sales.orders.print', 'sales.orders.invoice', 'sales.orders.pick-list'],
            ],
            'sales.price_override' => [
                'label' => 'Change Order Price',
                'group' => 'Sales',
                'routes' => [],
            ],
            'sales.customers' => [
                'label' => 'Customers',
                'group' => 'Sales',
                'routes' => ['sales.customers.index', 'sales.customers.create', 'sales.customers.edit', 'sales.customers.show'],
            ],
            'sales.invoices' => [
                'label' => 'Invoices',
                'group' => 'Sales',
                'routes' => ['sales.invoices.index', 'sales.invoices.pdf', 'sales.invoices.email', 'sales.invoices.pick-list'],
            ],
            'sales.payments' => [
                'label' => 'Payments & Credits',
                'group' => 'Sales',
                'routes' => ['sales.payments.index', 'sales.invoices.receipt'],
            ],
            'sales.credit_memos' => [
                'label' => 'Credit Memos',
                'group' => 'Sales',
                'routes' => ['sales.credit-memos.index', 'sales.credit-memos.pdf', 'sales.credit-memos.email'],
            ],
            'purchasing.orders' => [
                'label' => 'Purchase Orders',
                'group' => 'Purchasing',
                'routes' => ['purchasing.orders.index', 'purchasing.orders.create', 'purchasing.orders.edit', 'purchasing.orders.show', 'purchasing.orders.print'],
            ],
            'purchasing.receivings' => [
                'label' => 'Inventory Receivings',
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
            'reports.sales' => [
                'label' => 'Sales Reports',
                'group' => 'Reports',
                'routes' => [
                    'reports.sales',
                    'reports.sales-by-customer',
                    'reports.sales-by-item',
                    'reports.sales-by-categories',
                    'reports.sales-by-totals',
                    'reports.sales-by-stick-count',
                    'reports.sales-by-manufacturer',
                ],
            ],
            'reports.purchases' => [
                'label' => 'Purchase Reports',
                'group' => 'Reports',
                'routes' => [
                    'reports.purchases-by-stick-count',
                    'reports.purchases-by-item',
                ],
            ],
            'reports.price_list' => [
                'label' => 'Price List',
                'group' => 'Reports',
                'routes' => ['reports.price-list'],
            ],
            'reports.msa' => [
                'label' => 'MSA Report',
                'group' => 'Reports',
                'routes' => ['reports.msa', 'reports.msa.file'],
            ],
            'team.chat' => [
                'label' => 'Team Chat',
                'group' => 'Team chat',
                'routes' => ['team-chat.index'],
            ],
            'team.chat_manage' => [
                'label' => 'Create channels & add members',
                'group' => 'Team chat',
                'routes' => [],
            ],
            'lookups' => [
                'label' => 'Lookups',
                'group' => 'Lookups',
                'routes' => ['lookups.index'],
            ],
        ];
    }

    public static function keys(): array
    {
        return self::$keyList ??= array_keys(self::all());
    }

    /** @return list<string> All "feature.action" permission tokens. */
    public static function permissionTokens(): array
    {
        $tokens = [];
        foreach (self::keys() as $feature) {
            foreach (self::ACTIONS as $action) {
                $tokens[] = $feature.'.'.$action;
            }
        }

        return $tokens;
    }

    public static function token(string $feature, string $action): string
    {
        return $feature.'.'.$action;
    }

    /**
     * Expand stored permission list into feature => actions map.
     *
     * @param  list<string>|null  $raw
     * @return array<string, list<string>>|null  null = unrestricted (legacy)
     */
    public static function expand(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $cacheKey = implode("\0", $raw);
        if (array_key_exists($cacheKey, self::$expandCache)) {
            return self::$expandCache[$cacheKey];
        }

        $map = [];
        foreach ($raw as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            if (preg_match('/^(.*)\.(view|edit|delete)$/', $entry, $m) === 1) {
                $feature = $m[1];
                $action = $m[2];
                foreach (self::resolveFeatureTargets($feature) as $resolved) {
                    $map[$resolved] ??= [];
                    if (! in_array($action, $map[$resolved], true)) {
                        $map[$resolved][] = $action;
                    }
                }

                continue;
            }

            // Legacy bare feature key ⇒ all actions on resolved targets
            foreach (self::resolveLegacyBareFeature($entry) as $resolved) {
                $map[$resolved] = self::ACTIONS;
            }
        }

        return self::$expandCache[$cacheKey] = $map;
    }

    /**
     * Resolve a feature key from a "feature.action" token (no company/stamp side-grants).
     *
     * @return list<string>
     */
    public static function resolveFeatureTargets(string $feature): array
    {
        if (isset(self::LEGACY_ALIASES[$feature])) {
            return self::LEGACY_ALIASES[$feature];
        }

        if (isset(self::all()[$feature])) {
            return [$feature];
        }

        return [];
    }

    /**
     * Bare legacy keys may map to multiple current features.
     *
     * @return list<string>
     */
    public static function resolveLegacyBareFeature(string $feature): array
    {
        if (isset(self::LEGACY_ALIASES[$feature])) {
            return self::LEGACY_ALIASES[$feature];
        }

        // Company settings and stamp inventory used to share parent feature routes.
        if ($feature === 'admin.users') {
            return ['admin.users', 'admin.company'];
        }

        if ($feature === 'inventory.stock_counts') {
            return ['inventory.stock_counts', 'inventory.stamp_inventory'];
        }

        if (isset(self::all()[$feature])) {
            return [$feature];
        }

        return [];
    }

    /**
     * Flatten feature => actions map back to storage tokens.
     *
     * @param  array<string, list<string>>  $map
     * @return list<string>
     */
    public static function flatten(array $map): array
    {
        $tokens = [];
        foreach ($map as $feature => $actions) {
            if (! isset(self::all()[$feature])) {
                continue;
            }
            foreach (self::ACTIONS as $action) {
                if (in_array($action, $actions, true)) {
                    $tokens[] = self::token($feature, $action);
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    public static function featureForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        $map = self::routeFeatureMap();
        if (isset($map[$routeName])) {
            return $map[$routeName];
        }

        foreach ($map as $route => $key) {
            $prefix = preg_replace('/\.(index|create|edit|show|print|pdf|email|receipt|media)$/', '', $route);
            if ($prefix && (str_starts_with($routeName, $prefix.'.') || $routeName === $prefix)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected static function routeFeatureMap(): array
    {
        if (self::$routeFeatureMap !== null) {
            return self::$routeFeatureMap;
        }

        $map = [];
        foreach (self::all() as $key => $meta) {
            foreach ($meta['routes'] as $route) {
                $map[$route] = $key;
            }
        }

        return self::$routeFeatureMap = $map;
    }

    public static function actionForRoute(?string $routeName): string
    {
        if (! $routeName) {
            return 'view';
        }

        foreach (['.destroy', '.delete'] as $suffix) {
            if (str_ends_with($routeName, $suffix) || str_contains($routeName, $suffix.'.')) {
                return 'delete';
            }
        }

        foreach (['.create', '.edit', '.store', '.update', '.media'] as $needle) {
            if (str_ends_with($routeName, $needle) || str_contains($routeName, $needle.'.')) {
                return 'edit';
            }
        }

        return 'view';
    }

    /**
     * @return array<string, array<string, string>> group => [feature => label]
     */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::all() as $key => $meta) {
            $grouped[$meta['group']][$key] = $meta['label'];
        }

        return $grouped;
    }

    /**
     * Menu cards for the role permission UI — mirrors the top nav (menu → all submenus).
     *
     * @return array<string, list<array{label: string, feature: string}>>
     */
    public static function menuCards(): array
    {
        return [
            'File' => [
                ['label' => 'Company Settings', 'feature' => 'admin.company'],
                ['label' => 'Overselling Settings', 'feature' => 'admin.overselling'],
                ['label' => 'POS AI Settings', 'feature' => 'admin.japsai'],
                ['label' => 'POS AI Chat', 'feature' => 'admin.japsai_chat'],
                ['label' => 'Users & Roles', 'feature' => 'admin.users'],
                ['label' => 'Email Setup', 'feature' => 'admin.email_setup'],
                ['label' => 'Email Send Log', 'feature' => 'admin.email_logs'],
            ],
            'Inquiry' => [
                ['label' => 'Stock Status', 'feature' => 'inquiries.stock_status'],
                ['label' => 'Item Velocity', 'feature' => 'inquiries.item_velocity'],
            ],
            'Inventory' => [
                ['label' => 'Items', 'feature' => 'inventory.items'],
                ['label' => 'New Item', 'feature' => 'inventory.items'],
                ['label' => 'Stock Counts', 'feature' => 'inventory.stock_counts'],
                ['label' => 'Bulk Pricing', 'feature' => 'inventory.bulk_pricing'],
                ['label' => 'Stamp Inventory', 'feature' => 'inventory.stamp_inventory'],
            ],
            'Sales' => [
                ['label' => 'Sales Orders', 'feature' => 'sales.orders'],
                ['label' => 'New Sales Order', 'feature' => 'sales.orders'],
                ['label' => 'Change Order Price', 'feature' => 'sales.price_override'],
                ['label' => 'Customers', 'feature' => 'sales.customers'],
                ['label' => 'New Customer', 'feature' => 'sales.customers'],
                ['label' => 'Invoices', 'feature' => 'sales.invoices'],
                ['label' => 'Payments & Credits', 'feature' => 'sales.payments'],
                ['label' => 'Credit Memos', 'feature' => 'sales.credit_memos'],
            ],
            'Purchasing' => [
                ['label' => 'Purchase Orders', 'feature' => 'purchasing.orders'],
                ['label' => 'New Purchase Order', 'feature' => 'purchasing.orders'],
                ['label' => 'Inventory Receivings', 'feature' => 'purchasing.receivings'],
                ['label' => 'Return to Vendor', 'feature' => 'purchasing.rtv'],
                ['label' => 'Suppliers', 'feature' => 'purchasing.suppliers'],
                ['label' => 'New Supplier', 'feature' => 'purchasing.suppliers'],
            ],
            'Team chat' => [
                ['label' => 'Team Chat', 'feature' => 'team.chat'],
                ['label' => 'Create channels & add members', 'feature' => 'team.chat_manage'],
            ],
            'Lookups' => [
                ['label' => 'Lookups', 'feature' => 'lookups'],
            ],
            'Reports' => [
                ['label' => 'Sales Report By Customer', 'feature' => 'reports.sales'],
                ['label' => 'Sales Report By Item', 'feature' => 'reports.sales'],
                ['label' => 'Sales Report By Categories', 'feature' => 'reports.sales'],
                ['label' => 'Sales Report By Totals', 'feature' => 'reports.sales'],
                ['label' => 'Sales Report By Stick Count', 'feature' => 'reports.sales'],
                ['label' => 'Sales Report By Manufacturer', 'feature' => 'reports.sales'],
                ['label' => 'Purchases Report by Stick Count', 'feature' => 'reports.purchases'],
                ['label' => 'Purchases Report by Item', 'feature' => 'reports.purchases'],
                ['label' => 'Price List', 'feature' => 'reports.price_list'],
                ['label' => 'MSA Report', 'feature' => 'reports.msa'],
            ],
        ];
    }

    /**
     * Unique feature keys under a menu card (for group All/None).
     *
     * @return list<string>
     */
    public static function featuresForMenu(string $menu): array
    {
        $cards = self::menuCards();
        if (! isset($cards[$menu])) {
            return [];
        }

        return array_values(array_unique(array_column($cards[$menu], 'feature')));
    }

    /**
     * On by default for everyone. Uncheck View in Users & Roles to hide.
     * Manual off is stored as "feature.off".
     *
     * @return list<string>
     */
    public static function implicitOnFeatures(): array
    {
        return [
            'team.chat',
            'lookups',
        ];
    }

    public static function offToken(string $feature): string
    {
        return $feature.'.off';
    }

    public static function isImplicitlyOn(string $feature): bool
    {
        return in_array($feature, self::implicitOnFeatures(), true);
    }

    /**
     * Whether stored permissions grant an action.
     * Implicit features are allowed until "feature.off" is saved.
     *
     * @param  list<string>|null  $raw
     */
    public static function grants(?array $raw, string $feature, string $action = 'view'): bool
    {
        if ($raw === null) {
            return true;
        }

        if (in_array(self::offToken($feature), $raw, true)) {
            return false;
        }

        $map = self::expand($raw) ?? [];
        if (in_array($action, $map[$feature] ?? [], true)) {
            return true;
        }

        if (self::isImplicitlyOn($feature) && ! isset($map[$feature])) {
            return in_array($action, self::ACTIONS, true);
        }

        return false;
    }

    /**
     * Checkbox list: implicit features appear checked unless turned off.
     *
     * @param  list<string>|null  $raw
     * @return list<string>
     */
    public static function checkboxTokens(?array $raw): array
    {
        if ($raw === null) {
            return self::defaultRolePermissionTokens();
        }

        $off = [];
        $kept = [];
        foreach ($raw as $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }
            if (str_ends_with($token, '.off')) {
                $off[] = substr($token, 0, -4);
                continue;
            }
            $kept[] = $token;
        }

        foreach (self::implicitOnFeatures() as $feature) {
            if (in_array($feature, $off, true)) {
                continue;
            }
            $hasAny = false;
            foreach (self::ACTIONS as $action) {
                if (in_array(self::token($feature, $action), $kept, true)) {
                    $hasAny = true;
                    break;
                }
            }
            if (! $hasAny) {
                foreach (self::ACTIONS as $action) {
                    $kept[] = self::token($feature, $action);
                }
            }
        }

        return array_values(array_unique(array_intersect($kept, self::permissionTokens())));
    }

    /**
     * Persist checkboxes. Unchecked implicit features are stored as "feature.off".
     *
     * @param  list<string>  $checked
     * @return list<string>
     */
    public static function persistTokens(array $checked): array
    {
        $tokens = array_values(array_unique(array_intersect($checked, self::permissionTokens())));

        foreach (self::implicitOnFeatures() as $feature) {
            $hasAny = false;
            foreach (self::ACTIONS as $action) {
                if (in_array(self::token($feature, $action), $tokens, true)) {
                    $hasAny = true;
                    break;
                }
            }
            if (! $hasAny) {
                $tokens[] = self::offToken($feature);
            }
        }

        return $tokens;
    }

    /**
     * File-menu admin features — off by default; enable only when needed.
     *
     * @return list<string>
     */
    public static function restrictedAdminFeatures(): array
    {
        return [
            'admin.company',
            'admin.overselling',
            'admin.japsai',
            'admin.users',
            'admin.email_setup',
            'admin.email_logs',
        ];
    }

    /**
     * In-page capabilities that stay off until granted to a specific user (or role).
     *
     * @return list<string>
     */
    public static function restrictedCapabilities(): array
    {
        return [
            'sales.price_override',
            'admin.japsai_chat',
            'team.chat_manage',
        ];
    }

    /**
     * Features excluded from new non-admin roles and "reset from role".
     *
     * @return list<string>
     */
    public static function offByDefaultFeatures(): array
    {
        return array_values(array_unique([
            ...self::restrictedAdminFeatures(),
            ...self::restrictedCapabilities(),
        ]));
    }

    /** Whether a feature is a restricted File admin feature. */
    public static function isRestrictedAdminFeature(string $feature): bool
    {
        return in_array($feature, self::restrictedAdminFeatures(), true);
    }

    public static function isRestrictedCapability(string $feature): bool
    {
        return in_array($feature, self::restrictedCapabilities(), true);
    }

    /**
     * Permission tokens excluding in-page capabilities that stay off until granted per user.
     *
     * @return list<string>
     */
    public static function roleBulkPermissionTokens(): array
    {
        $blocked = self::restrictedCapabilities();

        return array_values(array_filter(
            self::permissionTokens(),
            function (string $token) use ($blocked): bool {
                foreach ($blocked as $feature) {
                    if ($token === $feature || str_starts_with($token, $feature.'.')) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * Default permission tokens for new non-admin roles (admin File items excluded).
     *
     * @return list<string>
     */
    public static function defaultRolePermissionTokens(): array
    {
        $blocked = self::offByDefaultFeatures();

        return array_values(array_filter(
            self::permissionTokens(),
            function (string $token) use ($blocked): bool {
                foreach ($blocked as $feature) {
                    if ($token === $feature || str_starts_with($token, $feature.'.')) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * Remove restricted File admin tokens from a permission list.
     *
     * @param  list<string>|null  $tokens
     * @return list<string>
     */
    public static function withoutRestrictedAdmin(?array $tokens): array
    {
        if (! is_array($tokens) || $tokens === []) {
            return [];
        }

        $blocked = self::offByDefaultFeatures();

        return array_values(array_filter(
            $tokens,
            function (string $token) use ($blocked): bool {
                foreach ($blocked as $feature) {
                    if ($token === $feature || str_starts_with($token, $feature.'.')) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }
}
