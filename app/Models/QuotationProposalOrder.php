<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class QuotationProposalOrder extends Model
{
    use HasUuids;

    /**
     * Attributes:
     *
     * $this->attributes['id'] - string - UUID primary key
     * $this->attributes['client_id'] - int|null - FK referencing the client that owns the quotation
     * $this->attributes['business_unit'] - string - Business unit associated with the quotation
     * $this->attributes['gestion_line'] - string|null - Gestion line(s) of the services picked (legacy requests)
     * $this->attributes['services'] - array - Services picked from the catalog (legacy requests; empty since the encuadre)
     * $this->attributes['answers'] - array - JSON-encoded list of answers provided in the quotation form
     * $this->attributes['quotation_url'] - string|null - URL of the generated quotation document
     */

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quotation_code',
        'client_id',
        'business_unit',
        'gestion_line',
        'services',
        'answers',
        'is_generated',
        'viewed_by_client',
        'quotation_url',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casting
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'services' => 'array',
        'answers' => 'array',
        'is_generated' => 'boolean',
        'viewed_by_client' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters & Setters
    |--------------------------------------------------------------------------
    */

    // ID
    public function getId(): string
    {
        return (string) $this->getAttribute('id');
    }

    public function getQuotationCode(): ?string
    {
        $code = trim((string) ($this->attributes['quotation_code'] ?? ''));

        return $code === '' ? null : $code;
    }

    // Client

    public function getClientId(): ?int
    {
        $v = $this->attributes['client_id'] ?? null;

        return $v === null ? null : (int) $v;
    }

    public function setClientId(?int $value): void
    {
        $this->attributes['client_id'] = $value;
    }

    // Business Unit

    public function getBusinessUnit(): string
    {
        return $this->attributes['business_unit'];
    }

    public function setBusinessUnit(string $value): void
    {
        $this->attributes['business_unit'] = $value;
    }

    // Gestion Line (only on requests made when the client picked services)
    public function getGestionLine(): ?string
    {
        return $this->attributes['gestion_line'] ?? null;
    }

    public function setGestionLine(string $value): void
    {
        $this->attributes['gestion_line'] = $value;
    }

    // Services
    public function getServices(): array
    {
        return $this->getAttribute('services') ?? [];
    }

    public function setServices(array $value): void
    {
        $this->setAttribute('services', $value);
    }

    // Answers
    public function getAnswers(): array
    {
        return $this->getAttribute('answers') ?? [];
    }

    public function setAnswers(array $value): void
    {
        $this->setAttribute('answers', $value);
    }

    // Is Generated
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

    // Quotation URL
    public function getQuotationUrl(): ?string
    {
        return $this->attributes['quotation_url'] ?? null;
    }

    public function setQuotationUrl(?string $value): void
    {
        $this->attributes['quotation_url'] = $value;
    }

    public function getReceiveQuotationSignedUrl(): ?string
    {
        if (! $this->getQuotationUrl()) {
            return null;
        }

        return URL::signedRoute(
            'quotation.receive',
            ['quotationId' => $this->getId()],
        );
    }

    // Created At
    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute('created_at');
    }

    // Updated At
    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute('updated_at');
    }
}
