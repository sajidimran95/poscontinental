<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

trait CustomizesDeskListColumns
{
    /** @var list<string> */
    public array $visibleColumns = [];

    public function applyColumnPicker($keys = null): void
    {
        $keys = $this->sanitizeColumnKeys(is_array($keys) ? $keys : []);
        if ($keys === []) {
            $keys = $this->defaultVisibleColumns();
        }
        $this->visibleColumns = $keys;
        $this->storeVisibleColumns($keys);
    }

    /**
     * @return array<string, array{label: string, type?: string}>
     */
    abstract protected function deskListColumnCatalog(): array;

    /** @return list<string> */
    abstract protected function defaultVisibleColumns(): array;

    abstract protected function visibleColumnsSessionKey(): string;

    /** @return list<string> */
    protected function normalizedVisibleColumns(): array
    {
        $keys = $this->sanitizeColumnKeys($this->visibleColumns);

        return $keys !== [] ? $keys : $this->defaultVisibleColumns();
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function sanitizeColumnKeys(array $keys): array
    {
        $catalog = $this->deskListColumnCatalog();
        $out = [];
        foreach ($keys as $key) {
            if (is_string($key) && isset($catalog[$key]) && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** @return list<string> */
    protected function loadVisibleColumns(): array
    {
        $saved = Session::get($this->visibleColumnsSessionKey(), []);
        if (! is_array($saved) || $saved === []) {
            $cached = Cache::get($this->visibleColumnsSessionKey(), []);
            $saved = is_array($cached) ? $cached : [];
        }

        $keys = $this->sanitizeColumnKeys($saved);

        return $keys !== [] ? $keys : $this->defaultVisibleColumns();
    }

    /** @param  list<string>  $keys */
    protected function storeVisibleColumns(array $keys): void
    {
        Session::put($this->visibleColumnsSessionKey(), $keys);
        Cache::forever($this->visibleColumnsSessionKey(), $keys);
    }

    protected function bootDeskListColumns(): void
    {
        $this->visibleColumns = $this->loadVisibleColumns();
    }

    /**
     * @return array{catalog: array<string, array{label: string, type?: string}>, keys: list<string>, colspan: int}
     */
    protected function deskListColumnViewData(int $extraCols = 1): array
    {
        $catalog = $this->deskListColumnCatalog();
        $keys = $this->normalizedVisibleColumns();

        return [
            'listColumnCatalog' => $catalog,
            'visibleColumnKeys' => $keys,
            'columnColspan' => count($keys) + $extraCols,
        ];
    }
}
