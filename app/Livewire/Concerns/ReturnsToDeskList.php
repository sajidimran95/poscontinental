<?php

namespace App\Livewire\Concerns;

use App\Services\DocumentTabManager;
use App\Support\PosDeskKey;
use App\Support\PosEmbed;

trait ReturnsToDeskList
{
    /**
     * After saving an edit/create document, close that tab and open the list.
     */
    protected function returnToDeskList(string $indexRoute, ?string $message = null): mixed
    {
        $message = trim((string) ($message ?? session('status') ?? ''));
        if ($message !== '') {
            session()->flash('status', $message);
        }

        $listUrl = route($indexRoute);
        $closeDesk = PosDeskKey::fromUrl(url()->current());

        app(DocumentTabManager::class)->closeMatchingUrl(url()->current());

        $payload = json_encode([
            'type' => 'pos-return-list',
            'list_url' => $listUrl,
            'close_desk' => $closeDesk,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->js(
            'try{if(window.parent&&window.parent!==window){window.parent.postMessage('.$payload.',window.location.origin)}}catch(e){}'
        );

        if (PosEmbed::isEmbed()) {
            $this->skipRender();

            return null;
        }

        $this->dispatch('pos-return-list', listUrl: $listUrl, closeDesk: $closeDesk, message: $message);

        return $this->redirect($listUrl, navigate: true);
    }
}
