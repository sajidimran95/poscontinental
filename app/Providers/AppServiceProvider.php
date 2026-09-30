<?php

namespace App\Providers;

use App\Models\DeliveryRoute;
use App\Policies\DeliveryRoutePolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(DeliveryRoute::class, DeliveryRoutePolicy::class);

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Authenticated::class,
            function ($event): void {
                // Only load role for User models, not Customer models
                if ($event->user instanceof \App\Models\User) {
                    $event->user->loadMissing('role');
                }
            }
        );

        \Illuminate\Support\Facades\View::composer('store.*', function ($view): void {
            $attrs = request()->attributes;
            if (! $attrs->has('store.view_shared')) {
                $companyId = \App\Support\Store\StoreContext::companyId();
                $attrs->set('store.view_shared', [
                    'shop' => \App\Support\Store\StoreContext::shopInfo(),
                    'nav_categories' => $companyId ? \App\Support\Store\StoreCatalog::headerCategories($companyId) : collect(),
                    'nav_brands' => $companyId ? \App\Support\Store\StoreCatalog::brands($companyId)->sortByDesc('products_count')->take(20)->values() : collect(),
                    'cart_totals' => ['count' => $companyId ? app(\App\Support\Store\StoreCart::class)->badgeCount() : 0],
                ]);
            }
            $view->with($attrs->get('store.view_shared'));
        });

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
