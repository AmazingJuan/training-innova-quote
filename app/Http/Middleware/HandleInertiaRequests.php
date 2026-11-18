<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly Settings $settings) {}

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            // Contact block of the public footer, editable in Configuración.
            'company' => fn () => $this->settings->companyContact(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                // Confirmation shown by the quote page itself: {code, email}.
                'quotation_sent' => $request->session()->get('quotation_sent'),
            ],
        ];
    }
}
