<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        $helpers = app_path('Support/helpers.php');
        if (is_file($helpers)) {
            require_once $helpers;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('userTime', function ($expression) {
            return "<?php echo user_time($expression); ?>";
        });

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
