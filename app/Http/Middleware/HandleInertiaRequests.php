<?php

namespace App\Http\Middleware;

use App\Models\Configuration;
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
            'company' => fn () => $this->company(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                // Confirmation shown by the quote page itself: {code, email}.
                'quotation_sent' => $request->session()->get('quotation_sent'),
            ],
        ];
    }

    /**
     * @return array{email: ?string, phone: ?string, address: ?string, website_url: ?string, website_label: ?string}
     */
    private function company(): array
    {
        $config = Configuration::current();

        return [
            'email' => $config?->getCompanyContactEmail(),
            'phone' => $config?->getCompanyContactPhone(),
            'address' => $config?->getCompanyContactAddress(),
            'website_url' => $config?->getCompanyWebsiteUrl(),
            'website_label' => $config?->getCompanyWebsiteLabel(),
        ];
    }
}
