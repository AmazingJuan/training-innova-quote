<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property string $abbreviation
 * @property int|null $quotation_seq_year
 * @property int $quotation_seq_value
 */
class BusinessUnit extends Model
{
    use Searchable;

    /**
     * Attributes:
     *
     * $this->attributes['id'] - int - Primary key identifier
     * $this->attributes['name'] - string - Unique name of the condition
     * $this->attributes['created_at'] - Carbon - Record creation timestamp
     * $this->attributes['updated_at'] - Carbon - Record last update timestamp
     * $this->attributes['services'] - Service[] - Services associated with the business unit
     */
    protected $fillable = ['name', 'display_name', 'initial_condition_id', 'abbreviation'];

    // Relationships

    public function initialCondition(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'initial_condition_id');
    }

    public function getInitialCondition(): ?Condition
    {
        return $this->initialCondition;
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(Condition::class);
    }

    public function getConditions(): Collection
    {
        return $this->conditions;
    }

    // Getters

    public function getId(): int
    {
        return $this->attributes['id'];
    }

    public function getName(): string
    {
        return $this->attributes['name'];
    }

    public function getDisplayName(): string
    {
        return $this->attributes['display_name'];
    }

    // Setters

    public function setName(string $name): void
    {
        $this->attributes['name'] = $name;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->attributes['display_name'] = $displayName;
    }

    // Relationships

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function getServices(): Collection
    {
        return $this->services;
    }

    public function getAbbreviation(): string
    {
        return strtoupper(trim((string) ($this->attributes['abbreviation'] ?? '')));
    }

    /**
     * Siguiente código tipo ABBREV-V-YYnn (año de 2 dígitos + V que indica que la cotización es Virtual + correlativo dentro del año).
     * Debe ejecutarse dentro de una transacción; bloquea la fila de la unidad.
     */
    public function lockAndAllocateNextQuotationCode(): string
    {
        $row = self::query()->whereKey($this->getId())->lockForUpdate()->firstOrFail();
        $yy = (int) now()->format('y');
        $storedYear = $row->quotation_seq_year !== null ? (int) $row->quotation_seq_year : -1;
        $val = (int) $row->quotation_seq_value;
        if ($storedYear !== $yy) {
            $storedYear = $yy;
            $val = 0;
        }
        $val++;
        $row->quotation_seq_year = $storedYear;
        $row->quotation_seq_value = $val;
        $row->save();

        return sprintf('%s-V-%02d%02d', strtoupper(trim((string) $row->abbreviation)), $yy, $val);
    }
}
