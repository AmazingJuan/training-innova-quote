<?php

namespace App\Providers;

use App\Models\Configuration;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('emails.partials.company-contact', function ($view) {
            $c = Configuration::current();
            $view->with([
                'companyContactEmail' => $c?->getCompanyContactEmail(),
                'companyContactPhone' => $c?->getCompanyContactPhone(),
                'companyContactAddress' => $c?->getCompanyContactAddress(),
                'companyWebsiteUrl' => $c?->getCompanyWebsiteUrl(),
                'companyWebsiteLabel' => $c?->getCompanyWebsiteLabel(),
            ]);
        });
    }
}
