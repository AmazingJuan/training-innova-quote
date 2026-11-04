<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row table with the application settings editable from the admin.
 */
class Configuration extends Model
{
    protected $fillable = [
        'notification_email',
        'company_contact_email',
        'company_contact_phone',
        'company_contact_address',
        'company_website_url',
        'company_website_label',
    ];

    /**
     * The settings row (created by migration). Null only on a broken install.
     */
    public static function current(): ?self
    {
        return static::query()->first();
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
