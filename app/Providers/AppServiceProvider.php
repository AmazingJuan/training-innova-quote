<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use App\Models\Configuration;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Mail;
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

        Mail::extend('msgraph', fn () => new MicrosoftGraphTransport(
            $this->app->make(HttpFactory::class),
            (string) config('services.msgraph.tenant_id'),
            (string) config('services.msgraph.client_id'),
            (string) config('services.msgraph.client_secret'),
            (string) config('services.msgraph.from'),
        ));

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
