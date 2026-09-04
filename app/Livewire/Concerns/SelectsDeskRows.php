<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Renderless;

trait SelectsDeskRows
{
    #[Renderless]
    public function selectRow(int $id): void
    {
        $this->selectedId = $id > 0 ? $id : null;
    }
}
