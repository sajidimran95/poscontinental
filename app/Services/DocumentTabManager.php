<?php

namespace App\Services;

use App\Support\PosDeskKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class DocumentTabManager
{
    public const SESSION_KEY = 'pos_document_tabs';

    public const MAX_TABS = 9;

    public const DRAFT_TTL_SECONDS = 43200;

    /**
     * Total open windows (document tabs + New Sales Order windows).
     */
    public const MAX_OPEN_WINDOWS = 9;

    public static function tabLimitMessage(): string
    {
        return self::MAX_OPEN_WINDOWS.' tabs are already open. Close 1 tab, then open this.';
    }

    /**
     * Routes that only keep one tab (re-click focuses existing).
     * sales.orders.create is handled by SalesOrderWindowManager instead.
     */
    public function isSingleton(string $route): bool
    {
        return $route !== 'sales.orders.create';
    }

    /**
     * List, create, and edit/show each keep their own tab slot.
     */
    public function tabFamily(string $route): string
    {
        if ($route === 'sales.orders.create' || $route === 'home') {
            return $route;
        }

        $parts = explode('.', $route);
        if (count($parts) < 2) {
            return $route;
        }

        $last = $parts[count($parts) - 1];
        if ($last === 'index' || $last === 'create') {
            return $route;
        }
        if (in_array($last, ['edit', 'show', 'print'], true)) {
            array_pop($parts);

            return implode('.', $parts).'.edit';
        }

        return $route;
    }

    /**
     * Prefer the list/index label for a tab family.
     */
    public function familyLabel(string $route, string $fallback): string
    {
        $family = $this->tabFamily($route);

        $createMap = [
            'inventory.items.create' => 'New Item',
            'sales.customers.create' => 'New Customer',
            'purchasing.suppliers.create' => 'New Supplier',
            'purchasing.orders.create' => 'New Purchase Order',
        ];
        if (isset($createMap[$family])) {
            return $createMap[$family];
        }
        if (str_ends_with($family, '.create')) {
            return 'New '.str($family)->beforeLast('.create')->afterLast('.')->singular()->headline()->toString();
        }

        $editMap = [
            'inventory.items.edit' => 'Item',
            'sales.orders.edit' => 'Order',
            'sales.customers.edit' => 'Customer',
            'sales.invoices.edit' => 'Invoice',
            'sales.credit-memos.edit' => 'Credit Memo',
            'purchasing.orders.edit' => 'Purchase Order',
            'purchasing.suppliers.edit' => 'Supplier',
            'purchasing.receivings.edit' => 'Receiving',
            'purchasing.rtv.edit' => 'RTV',
            'inventory.stock-counts.edit' => 'Stock Count',
        ];
        if (isset($editMap[$family])) {
            return $editMap[$family];
        }
        if (str_ends_with($family, '.edit')) {
            return str($family)->beforeLast('.edit')->afterLast('.')->singular()->headline()->toString();
        }

        $index = str_ends_with($family, '.index') ? $family : $family.'.index';
        $map = [
            'purchasing.receivings.index' => 'Receivings',
            'purchasing.orders.index' => 'Purchase Orders',
            'purchasing.suppliers.index' => 'Suppliers',
            'purchasing.rtv.index' => 'RTV',
            'sales.orders.index' => 'Orders',
            'sales.customers.index' => 'Customers',
            'sales.invoices.index' => 'Invoices',
            'sales.credit-memos.index' => 'Credit Memos',
            'inventory.items.index' => 'Items',
            'inventory.stock-counts.index' => 'Stock Counts',
        ];

        return $map[$index] ?? $fallback;
    }

    /**
     * @return array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}
     */
    public function state(): array
    {
        $raw = session(self::SESSION_KEY);
        if (! is_array($raw) || ! isset($raw['tabs']) || ! is_array($raw['tabs']) || $raw['tabs'] === []) {
            $cached = $this->readCache();
            if ($cached !== null) {
                session([self::SESSION_KEY => $cached]);
                $raw = $cached;
            }
        }

        if (! is_array($raw) || ! isset($raw['tabs']) || ! is_array($raw['tabs'])) {
            return ['tabs' => [], 'active' => null];
        }

        $tabs = [];
        $seenFamilies = [];
        $changed = false;
        foreach ($raw['tabs'] as $row) {
            if (! is_array($row) || empty($row['id']) || empty($row['route']) || empty($row['url'])) {
                continue;
            }
            // SO create windows live in SalesOrderWindowManager
            if (($row['route'] ?? '') === 'sales.orders.create') {
                continue;
            }
            $route = (string) $row['route'];
            $family = $this->tabFamily($route);
            if (isset($seenFamilies[$family])) {
                $changed = true;
                continue;
            }
            $seenFamilies[$family] = true;
            $url = (string) $row['url'];
            // Repair tabs whose URL was overwritten to Home by rememberUrl.
            $urlPath = parse_url($url, PHP_URL_PATH) ?: '';
            $urlPath = rtrim((string) $urlPath, '/') ?: '/';
            if (($urlPath === '/home' || $urlPath === '/') && Route::has($route)) {
                try {
                    $url = route($route);
                    $changed = true;
                } catch (\Throwable) {
                    // keep existing url
                }
            }
            $tabs[] = [
                'id' => (string) $row['id'],
                'label' => $this->familyLabel($route, (string) ($row['label'] ?? $row['route'])),
                'route' => $route,
                'url' => $url,
            ];
        }

        $active = isset($raw['active']) && is_string($raw['active']) ? $raw['active'] : null;
        if ($active !== null && ! collect($tabs)->contains(fn (array $t) => $t['id'] === $active)) {
            $active = $tabs[0]['id'] ?? null;
            $changed = true;
        }

        if ($changed) {
            $this->put(['tabs' => $tabs, 'active' => $active]);
        }

        return ['tabs' => $tabs, 'active' => $active];
    }

    /**
     * @return list<array{id: string, label: string, route: string, url: string}>
     */
    public function list(): array
    {
        return $this->state()['tabs'];
    }

    public function count(): int
    {
        return count($this->state()['tabs']);
    }

    public function activeId(): ?string
    {
        return $this->state()['active'];
    }

    public function has(string $id): bool
    {
        return collect($this->state()['tabs'])->contains(fn (array $t) => $t['id'] === $id);
    }

    /**
     * Open or focus a document tab. Returns tab id.
     */
    public function openOrFocus(string $label, string $route, string $url, bool $updateUrl = true): string
    {
        if ($route === 'sales.orders.create' || $route === 'home') {
            return '';
        }

        $state = $this->state();
        $family = $this->tabFamily($route);
        $label = $this->familyLabel($route, $label);

        if ($this->isSingleton($route)) {
            foreach ($state['tabs'] as $i => $tab) {
                if ($this->tabFamily($tab['route']) === $family) {
                    $state['tabs'][$i]['label'] = $label;
                    $state['tabs'][$i]['route'] = $route;
                    if ($updateUrl && $url !== '') {
                        $state['tabs'][$i]['url'] = $url;
                    }
                    $state['active'] = $tab['id'];
                    $this->put($state);

                    return $tab['id'];
                }
            }
        } else {
            foreach ($state['tabs'] as $tab) {
                if ($tab['url'] === $url) {
                    $state['active'] = $tab['id'];
                    $this->put($state);

                    return $tab['id'];
                }
            }
        }

        if (count($state['tabs']) >= self::MAX_TABS
            || (count($state['tabs']) + app(SalesOrderWindowManager::class)->count()) >= self::MAX_OPEN_WINDOWS) {
            return '';
        }

        $id = (string) Str::uuid();
        $state['tabs'][] = [
            'id' => $id,
            'label' => $label,
            'route' => $route,
            'url' => $url,
        ];
        $state['active'] = $id;
        $this->put($state);

        return $id;
    }

    /**
     * Sync the current request page into the tab strip.
     */
    public function syncCurrent(?string $routeName, string $label, string $url): void
    {
        if (! $routeName || $routeName === 'home' || $routeName === 'sales.orders.create') {
            return;
        }

        if (! Route::has($routeName) && ! str_contains($routeName, '.')) {
            return;
        }

        $this->openOrFocus($label, $routeName, $url, true);
    }

    /**
     * Persist a tab for a page URL without dropping other desks (list stays when edit opens).
     *
     * @return array{id: string, label: string, route: string, url: string}|null
     */
    public function ensureFromUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parts = parse_url($url);
            $path = ($parts['path'] ?? '/').(isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '');
        } elseif (str_starts_with($url, '/')) {
            $path = $url;
        } else {
            return null;
        }

        $pathOnly = parse_url($path, PHP_URL_PATH) ?: $path;
        $deskKey = PosDeskKey::fromUrl($path);
        if ($deskKey === '/home' || str_starts_with($deskKey, 'so:')) {
            return null;
        }

        try {
            $match = Route::getRoutes()->match(Request::create($pathOnly, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        $routeName = $match->getName();
        if (! $routeName || $routeName === 'home' || $routeName === 'sales.orders.create' || $routeName === 'pos.tabs.open') {
            return null;
        }

        $full = url($path);
        $label = $this->familyLabel($routeName, str($routeName)->afterLast('.')->headline()->toString());
        $id = $this->openOrFocus($label, $routeName, $full, true);
        if ($id === '') {
            return null;
        }

        return $this->find($id);
    }

    public function rememberUrl(string $url): void
    {
        $url = trim($url);
        if ($url === '') {
            return;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parts = parse_url($url);
            $path = ($parts['path'] ?? '/').(isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '');
        } elseif (str_starts_with($url, '/')) {
            $path = $url;
        } else {
            return;
        }

        $deskKey = PosDeskKey::fromUrl($path);
        // Never write Home onto a document tab — that collapses desk-keys and dual-activates tabs.
        if ($deskKey === '/home') {
            $this->clearActive();

            return;
        }

        $state = $this->state();
        $full = url($path);

        foreach ($state['tabs'] as $i => $tab) {
            if (PosDeskKey::fromUrl((string) $tab['url']) !== $deskKey) {
                continue;
            }
            $state['tabs'][$i]['url'] = $full;
            $state['active'] = $tab['id'];
            $this->put($state);

            return;
        }

        // Active tab only — and only if the new URL still belongs to that tab's desk.
        $active = $state['active'];
        if (! $active) {
            return;
        }
        foreach ($state['tabs'] as $i => $tab) {
            if ($tab['id'] !== $active) {
                continue;
            }
            if (PosDeskKey::fromUrl((string) $tab['url']) === $deskKey) {
                $state['tabs'][$i]['url'] = $full;
                $this->put($state);
            }

            return;
        }
    }

    public function clearActive(): void
    {
        $state = $this->state();
        if ($state['active'] === null) {
            return;
        }
        $state['active'] = null;
        $this->put($state);
    }

    public function setActive(string $id): void
    {
        $state = $this->state();
        if (! collect($state['tabs'])->contains(fn (array $t) => $t['id'] === $id)) {
            return;
        }
        $state['active'] = $id;
        $this->put($state);
    }

    /**
     * Close a tab.
     *
     * @return string|null Next URL, null for Home, or "__back__" to stay on current page
     */
    public function close(string $id): ?string
    {
        $state = $this->state();
        $index = null;
        foreach ($state['tabs'] as $i => $tab) {
            if ($tab['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return '__back__';
        }

        $wasActive = $state['active'] === $id;
        array_splice($state['tabs'], $index, 1);

        if ($state['tabs'] === []) {
            $state['active'] = null;
            $this->put($state);
            $this->forgetCache();

            return $wasActive ? null : '__back__';
        }

        if ($wasActive) {
            $neighbor = $state['tabs'][max(0, $index - 1)] ?? $state['tabs'][0];
            $state['active'] = $neighbor['id'];
            $this->put($state);

            return $neighbor['url'];
        }

        $this->put($state);

        return '__back__';
    }

    /**
     * Close the document tab whose desk matches this URL (list/edit stay separate).
     */
    public function closeMatchingUrl(string $url): void
    {
        $key = PosDeskKey::fromUrl($url);
        if ($key === '/home' || str_starts_with($key, 'so:')) {
            return;
        }

        foreach ($this->state()['tabs'] as $tab) {
            if (PosDeskKey::fromUrl((string) $tab['url']) === $key) {
                $this->close($tab['id']);

                return;
            }
        }
    }

    /**
     * Close every document tab (not SO create windows).
     */
    public function closeAll(): void
    {
        $this->put(['tabs' => [], 'active' => null]);
        $this->forgetCache();
    }

    public function find(string $id): ?array
    {
        foreach ($this->state()['tabs'] as $tab) {
            if ($tab['id'] === $id) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * @param  array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}  $state
     */
    protected function put(array $state): void
    {
        session([self::SESSION_KEY => $state]);
        $this->writeCache($state);
    }

    protected function cacheKey(): ?string
    {
        $userId = auth()->id();
        if (! $userId) {
            return null;
        }

        return 'pos_document_tabs:user:'.$userId;
    }

    /**
     * @return array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}|null
     */
    protected function readCache(): ?array
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return null;
        }

        $cached = Cache::get($key);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}  $state
     */
    protected function writeCache(array $state): void
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return;
        }

        if ($state['tabs'] === []) {
            Cache::forget($key);

            return;
        }

        Cache::put($key, $state, self::DRAFT_TTL_SECONDS);
    }

    protected function forgetCache(): void
    {
        $key = $this->cacheKey();
        if ($key !== null) {
            Cache::forget($key);
        }
    }
}
