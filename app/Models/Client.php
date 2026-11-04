<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Client extends Model
{
    use Searchable;

    /** Columns matched by the free-text search (also used by quotation orders). */
    public const SEARCHABLE_COLUMNS = ['name', 'email', 'company', 'nit', 'phone', 'role'];

    /**
     * Attributes:
     *
     * $this->attributes['id'] - int - Primary key identifier
     * $this->attributes['name'] - string - Client full name
     * $this->attributes['email'] - string - Unique client email (lowercased)
     * $this->attributes['company'] - string|null - Company the client represents
     * $this->attributes['phone'] - string - Contact phone number
     * $this->attributes['role'] - string|null - Role/position of the client
     * $this->attributes['nit'] - string|null - Tax id (NIT) of the company
     * $this->attributes['economic_activity'] - string|null - Economic activity of the company
     * $this->attributes['address'] - string|null - Address of the company
     * $this->attributes['created_at'] - Carbon - Record creation timestamp
     * $this->attributes['updated_at'] - Carbon - Record last update timestamp
     */

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'company',
        'phone',
        'role',
        'nit',
        'economic_activity',
        'address',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function quotationProposalOrders(): HasMany
    {
        return $this->hasMany(QuotationProposalOrder::class, 'client_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes (shared by the clients list and the Excel export)
    |--------------------------------------------------------------------------
    */

    /**
     * Derived quotation counters, usable with withCount() / loadCount().
     *
     * @return array<int|string, mixed>
     */
    public static function statsCounts(): array
    {
        return [
            'quotationProposalOrders as quotations_count',
            'quotationProposalOrders as generated_count' => fn (Builder $q) => $q->where('is_generated', true),
            'quotationProposalOrders as pending_count' => fn (Builder $q) => $q->where('is_generated', false),
        ];
    }

    /**
     * Attach the derived counters and the latest-quotation timestamp.
     */
    public function scopeWithClientStats(Builder $query): Builder
    {
        return $query
            ->withCount(self::statsCounts())
            ->withMax('quotationProposalOrders as last_quotation_at', 'created_at');
    }

    /**
     * @param  array{search?:string, company?:string, business_unit?:string}  $filters
     */
    public function scopeApplyClientFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['search'] ?? '', self::SEARCHABLE_COLUMNS)
            ->when(($filters['company'] ?? '') !== '', fn (Builder $q) => $q->where('company', $filters['company']))
            ->when(($filters['business_unit'] ?? '') !== '', fn (Builder $q) => $q->whereHas(
                'quotationProposalOrders',
                fn (Builder $q2) => $q2->where('business_unit', $filters['business_unit'])
            ));
    }

    public function scopeOrderByActivity(Builder $query): Builder
    {
        return $query->orderByDesc('last_quotation_at')->orderBy('name');
    }

    /**
     * Find the client by e-mail (case-insensitive) or create it, refreshing the
     * contact data with the latest values provided.
     *
     * @param  array{name:string, email:string, company?:string|null, phone?:string|null, role?:string|null, nit?:string|null, economic_activity?:string|null, address?:string|null}  $contact
     */
    public static function upsertFromContact(array $contact): self
    {
        $blankToNull = fn (?string $value) => ($value = trim((string) $value)) === '' ? null : $value;

        return static::updateOrCreate(
            ['email' => strtolower(trim($contact['email']))],
            [
                'name' => trim($contact['name']),
                'company' => $blankToNull($contact['company'] ?? null),
                'phone' => $blankToNull($contact['phone'] ?? null),
                'role' => $blankToNull($contact['role'] ?? null),
                'nit' => $blankToNull($contact['nit'] ?? null),
                'economic_activity' => $blankToNull($contact['economic_activity'] ?? null),
                'address' => $blankToNull($contact['address'] ?? null),
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Getters & Setters
    |--------------------------------------------------------------------------
    */

    public function getId(): int
    {
        return $this->attributes['id'];
    }

    public function getName(): string
    {
        return $this->attributes['name'];
    }

    public function setName(string $value): void
    {
        $this->attributes['name'] = $value;
    }

    public function getEmail(): string
    {
        return $this->attributes['email'];
    }

    public function setEmail(string $value): void
    {
        $this->attributes['email'] = $value;
    }

    public function getCompany(): ?string
    {
        return $this->attributes['company'] ?? null;
    }

    public function setCompany(?string $value): void
    {
        $this->attributes['company'] = $value;
    }

    public function getPhone(): ?string
    {
        return $this->attributes['phone'] ?? null;
    }

    public function setPhone(?string $value): void
    {
        $this->attributes['phone'] = $value;
    }

    public function getRole(): ?string
    {
        return $this->attributes['role'] ?? null;
    }

    public function getNit(): ?string
    {
        return $this->attributes['nit'] ?? null;
    }

    public function getEconomicActivity(): ?string
    {
        return $this->attributes['economic_activity'] ?? null;
    }

    public function getAddress(): ?string
    {
        return $this->attributes['address'] ?? null;
    }

    public function setRole(?string $value): void
    {
        $this->attributes['role'] = $value;
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
