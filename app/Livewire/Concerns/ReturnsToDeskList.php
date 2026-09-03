<?php

namespace App\Livewire\Concerns;

use App\Services\DocumentTabManager;
use App\Support\PosDeskKey;

trait ReturnsToDeskList
{
    /**
     * After saving an edit/create document, close that tab and open the list.
     */
    protected function returnToDeskList(string $indexRoute): mixed
    {
        $listUrl = route($indexRoute);
        $closeDesk = PosDeskKey::fromUrl(url()->current());

        app(DocumentTabManager::class)->closeMatchingUrl(url()->current());

        $this->dispatch('pos-return-list', listUrl: $listUrl, closeDesk: $closeDesk);

        return $this->redirect($listUrl, navigate: true);
    }
}
