<?php

namespace App\Models;

use Database\Factories\QuotationProposalOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * A quotation request: the client's answers to a business unit's form, and
 * later the link to the quotation management prepared for it.
 */
class QuotationProposalOrder extends Model
{
    /** @use HasFactory<QuotationProposalOrderFactory> */
    use HasFactory, HasUuids;

    /**
     * Attributes:
     *
     * $this->attributes['id'] - string - UUID primary key
     * $this->attributes['quotation_code'] - string|null - Readable code (EO-V-2601…)
     * $this->attributes['client_id'] - int|null - FK of the client who asked for it
     * $this->attributes['business_unit_id'] - int - FK of the business unit it was asked to
     * $this->attributes['answers'] - array - The answers, keyed by question (as the client read it)
     * $this->attributes['offer_date'] - Carbon|null - Date the client expects the offer by
     * $this->attributes['is_generated'] - bool - Whether management uploaded the quotation
     * $this->attributes['viewed_by_client'] - bool - Whether the client opened the quotation link
     * $this->attributes['quotation_url'] - string|null - URL of the quotation document
     * $this->attributes['reminders_sent'] - int - Reminders management received about it
     * $this->attributes['last_reminded_at'] - Carbon|null - When the last reminder went out
     */

    /**
     * Mass assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quotation_code',
        'client_id',
        'business_unit_id',
        'answers',
        'offer_date',
        'is_generated',
        'viewed_by_client',
        'quotation_url',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'offer_date' => 'date:Y-m-d',
            'is_generated' => 'boolean',
            'viewed_by_client' => 'boolean',
            'reminders_sent' => 'integer',
            'last_reminded_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    /** @return BelongsTo<BusinessUnit, $this> */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function getBusinessUnit(): BusinessUnit
    {
        return $this->businessUnit;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Requests still waiting for their quotation.
     *
     * @param  Builder<QuotationProposalOrder>  $query
     * @return Builder<QuotationProposalOrder>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_generated', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Getters & Setters
    |--------------------------------------------------------------------------
    */

    public function getId(): string
    {
        return (string) $this->getAttribute('id');
    }

    public function getQuotationCode(): ?string
    {
        $code = trim((string) ($this->attributes['quotation_code'] ?? ''));

        return $code === '' ? null : $code;
    }

    /** The code, or the id when the request has none (very old requests). */
    public function getReference(): string
    {
        return $this->getQuotationCode() ?? '#'.$this->getId();
    }

    public function getClientId(): ?int
    {
        $value = $this->attributes['client_id'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function setClientId(?int $value): void
    {
        $this->attributes['client_id'] = $value;
    }

    public function getBusinessUnitId(): int
    {
        return (int) $this->attributes['business_unit_id'];
    }

    public function getBusinessUnitName(): string
    {
        return $this->getBusinessUnit()->getDisplayName();
    }

    /** @return array<string, string> */
    public function getAnswers(): array
    {
        return $this->getAttribute('answers') ?? [];
    }

    /** @param array<string, string> $value */
    public function setAnswers(array $value): void
    {
        $this->setAttribute('answers', $value);
    }

    public function getOfferDate(): ?Carbon
    {
        return $this->offer_date;
    }

    public function setOfferDate(?Carbon $value): void
    {
        $this->offer_date = $value;
    }

    /** Whether the offer date the client asked for has already gone by. */
    public function isOverdue(Carbon $now): bool
    {
        $date = $this->getOfferDate();

        return $date !== null && $date->lt($now->copy()->startOfDay());
    }

    public function getIsGenerated(): bool
    {
        return (bool) ($this->attributes['is_generated'] ?? false);
    }

    public function setIsGenerated(bool $value): void
    {
        $this->attributes['is_generated'] = $value;
    }

    public function getViewedByClient(): bool
    {
        return (bool) ($this->attributes['viewed_by_client'] ?? false);
    }

    public function setViewedByClient(bool $value): void
    {
        $this->attributes['viewed_by_client'] = $value;
    }

    public function getQuotationUrl(): ?string
    {
        return $this->attributes['quotation_url'] ?? null;
    }

    public function setQuotationUrl(?string $value): void
    {
        $this->attributes['quotation_url'] = $value;
    }

    public function getRemindersSent(): int
    {
        return (int) ($this->attributes['reminders_sent'] ?? 0);
    }

    public function setRemindersSent(int $value, Carbon $at): void
    {
        $this->attributes['reminders_sent'] = $value;
        $this->last_reminded_at = $at;
    }

    /** Signed link the client receives by e-mail; null until there is a quotation. */
    public function getReceiveQuotationSignedUrl(): ?string
    {
        if (! $this->getQuotationUrl()) {
            return null;
        }

        return URL::signedRoute('quotation.receive', ['quotationId' => $this->getId()]);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute('created_at');
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute('updated_at');
    }
}
