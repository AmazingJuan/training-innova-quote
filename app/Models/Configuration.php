<?php

namespace App\Models;

use App\Services\Settings;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row table with the application settings editable from the admin.
 */
class Configuration extends Model
{
    /**
     * Attributes:
     *
     * $this->attributes['id'] - int - Primary key (there is a single row)
     * $this->attributes['notification_email'] - string|null - Inbox of management's notifications
     * $this->attributes['company_contact_email'] - string|null - Contact e-mail shown in the footer and e-mails
     * $this->attributes['company_contact_phone'] - string|null - Contact phone
     * $this->attributes['company_contact_address'] - string|null - Address
     * $this->attributes['company_website_url'] - string|null - Corporate website
     * $this->attributes['company_website_label'] - string|null - Text of the website link
     */

    /** @var list<string> */
    protected $fillable = [
        'notification_email',
        'company_contact_email',
        'company_contact_phone',
        'company_contact_address',
        'company_website_url',
        'company_website_label',
    ];

    /**
     * Read through App\Services\Settings (cached); saving the row clears that cache.
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(Settings::class)->forget());
    }

    public function getId(): int
    {
        return $this->attributes['id'];
    }

    public function getNotificationEmail(): ?string
    {
        return $this->nullableTrimmedString('notification_email');
    }

    public function getCompanyContactEmail(): ?string
    {
        return $this->nullableTrimmedString('company_contact_email');
    }

    public function getCompanyContactPhone(): ?string
    {
        return $this->nullableTrimmedString('company_contact_phone');
    }

    public function getCompanyContactAddress(): ?string
    {
        return $this->nullableTrimmedString('company_contact_address');
    }

    public function getCompanyWebsiteUrl(): ?string
    {
        return $this->nullableTrimmedString('company_website_url');
    }

    public function getCompanyWebsiteLabel(): ?string
    {
        return $this->nullableTrimmedString('company_website_label');
    }

    private function nullableTrimmedString(string $key): ?string
    {
        $value = trim((string) ($this->attributes[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
