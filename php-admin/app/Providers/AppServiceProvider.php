<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Join links, QR, asset() and redirects use APP_URL — set correctly per environment.
        $root = rtrim((string) config('app.url'), '/');
        if ($root !== '') {
            URL::forceRootUrl($root);
            if (str_starts_with($root, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // @vasset('css/admin.css') → URL asset kèm ?v=filemtime (cache-bust tập trung,
        // thay cho pattern filemtime() lặp trong từng Blade).
        Blade::directive('vasset', function (string $expression) {
            return "<?php echo \\App\\Support\\AssetVersion::url({$expression}); ?>";
        });
    }
}
