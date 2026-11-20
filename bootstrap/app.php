<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Nginx (u otro proxy) termina TLS; Laravel debe confiar en X-Forwarded-Proto para generar URLs https.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('clients:clean-exports')->hourly();
        $schedule->command('quotations:clean-attachments')->hourly();
        // Reminders to management: 8:00 a. m. and 2:00 p. m., Monday to Friday (holidays are skipped by the command).
        $schedule->command('quotations:remind-management')
            ->cron('0 8,14 * * 1-5')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping();
    })->create();
