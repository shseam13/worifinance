<?php

namespace App\Providers;

use App\Support\CurrencyFormatter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrencyFormatter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('money', static function (string $expression): string {
            return "<?php echo e(app(\\App\\Support\\CurrencyFormatter::class)->format({$expression})); ?>";
        });
    }
}
