<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use App\Models\Configuration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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

        // The public request form is anonymous and every request mails its sender as
        // gerencia@: without a limit a script could use it to mail strangers.
        RateLimiter::for('quotation-requests', function (Request $request) {
            $tooMany = fn () => back()->withErrors([
                'request' => 'Se han enviado demasiadas solicitudes desde su conexión. Intente de nuevo más tarde o comuníquese con nosotros por teléfono o correo.',
            ]);

            return [
                Limit::perHour(10)->by('hour:'.$request->ip())->response($tooMany),
                Limit::perDay(30)->by('day:'.$request->ip())->response($tooMany),
            ];
        });

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
