<?php

namespace App\Services;

use App\Models\Configuration;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The settings editable in Configuración, read once and cached: they are
 * needed on every page (the footer's contact block) and by every e-mail.
 *
 * Registered as a singleton; the cache is cleared when the row is saved
 * (see Configuration::booted).
 */
class Settings
{
    public const CACHE_KEY = 'settings.configuration';

    private ?Configuration $configuration = null;

    public function __construct(private readonly Cache $cache) {}

    public function configuration(): Configuration
    {
        return $this->configuration ??= (new Configuration)->setRawAttributes(
            $this->cache->rememberForever(self::CACHE_KEY, fn () => Configuration::query()->firstOrFail()->getAttributes()),
            sync: true,
        );
    }

    /** Inbox of management's notifications (new requests, reminders). */
    public function notificationEmail(): ?string
    {
        return $this->configuration()->getNotificationEmail();
    }

    /** @return array{email: ?string, phone: ?string, address: ?string, website_url: ?string, website_label: ?string} */
    public function companyContact(): array
    {
        $config = $this->configuration();

        return [
            'email' => $config->getCompanyContactEmail(),
            'phone' => $config->getCompanyContactPhone(),
            'address' => $config->getCompanyContactAddress(),
            'website_url' => $config->getCompanyWebsiteUrl(),
            'website_label' => $config->getCompanyWebsiteLabel(),
        ];
    }

    public function forget(): void
    {
        $this->configuration = null;
        $this->cache->forget(self::CACHE_KEY);
    }
}
