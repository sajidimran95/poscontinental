<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

trait PersistsDeskTabSearch
{
    protected function deskTabSearchBagKey(): string
    {
        $name = Route::currentRouteName() ?: 'list';

        return 'desk_tab_search.'.$name.'.'.(int) auth()->id();
    }

    protected function deskTabSearchSlot(): string
    {
        return property_exists($this, 'favorite') ? (string) $this->favorite : '_';
    }

    /** @return array<string, string> */
    protected function deskTabSearchBag(): array
    {
        $bag = Session::get($this->deskTabSearchBagKey(), []);
        if (! is_array($bag) || $bag === []) {
            $cached = Cache::get($this->deskTabSearchBagKey(), []);
            $bag = is_array($cached) ? $cached : [];
        }

        return is_array($bag) ? $bag : [];
    }

    protected function rememberDeskTabSearch(): void
    {
        if (! property_exists($this, 'search')) {
            return;
        }

        $bag = $this->deskTabSearchBag();
        $bag[$this->deskTabSearchSlot()] = (string) $this->search;
        Session::put($this->deskTabSearchBagKey(), $bag);
        Cache::forever($this->deskTabSearchBagKey(), $bag);
    }

    protected function hydrateDeskTabSearchFromStore(): void
    {
        if (! property_exists($this, 'search')) {
            return;
        }

        if (trim((string) $this->search) !== '') {
            $this->rememberDeskTabSearch();

            return;
        }

        $this->restoreDeskTabSearch();
    }

    protected function restoreDeskTabSearch(): void
    {
        if (! property_exists($this, 'search')) {
            return;
        }

        $bag = $this->deskTabSearchBag();
        $slot = $this->deskTabSearchSlot();
        if (array_key_exists($slot, $bag)) {
            $this->search = (string) $bag[$slot];
        }
    }

    public function mountPersistsDeskTabSearch(): void
    {
        $this->hydrateDeskTabSearchFromStore();
    }

    public function updatingSearch($value): void
    {
        if (method_exists($this, 'resetDeskList')) {
            $this->resetDeskList();
        }
    }

    public function updatedSearch(): void
    {
        $this->rememberDeskTabSearch();
    }

    public function updatingFavorite($value): void
    {
        $this->rememberDeskTabSearch();
    }
}
