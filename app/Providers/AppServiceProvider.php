<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Livewire\after;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire 3 puts StreamedResponse/BinaryFileResponse into effects.returns,
        // which cannot be JSON-encoded ("Type is not supported"). Fixed in Livewire 4
        // (livewire/livewire#10327); neutralize those returns after the download effect is stored.
        after('call', function () {
            return function ($return) {
                if ($return instanceof StreamedResponse || $return instanceof BinaryFileResponse) {
                    return false;
                }
            };
        });
    }
}
