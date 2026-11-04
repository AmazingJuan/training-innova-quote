<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Condition extends Model
{
    /**
     * Blocks of the "formato de encuadre", in the order the client goes through them.
     */
    public const SECTIONS = ['necesidad', 'condiciones', 'cotizacion', 'cierre'];

    /**
     * Attributes:
     *
     * $this->attributes['id'] - int - Primary key identifier
     * $this->attributes['label'] - string - Unique name of the condition
     * $this->attributes['interaction_type'] - string - Specifies what can the user do with the condition
     * $this->attributes['type'] - string - Input type (default: "text")
     * $this->attributes['observation'] - string|null - Description of the condition
     * $this->attributes['next_condition_id'] - int|null - ID of the next condition in sequence
     * $this->attributes['business_unit_id'] - int - ID of the associated business unit
     * $this->attributes['section'] - string|null - Block of the encuadre it belongs to (see SECTIONS)
     * $this->attributes['required'] - bool - Whether the client must answer it
     * $this->attributes['placeholder'] - string|null - Example of the expected answer, shown inside the field
     * $this->attributes['multiline'] - bool - Free text answered in a long field
     */

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'label',
        'interaction_type',
        'type',
        'observation',
        'next_condition_id',
        'business_unit_id',
        'multiple',
        'section',
        'required',
        'placeholder',
        'multiline',
    ];

    public $timestamps = false;

    protected $casts = [
        'multiple' => 'boolean',
        'required' => 'boolean',
        'multiline' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function options(): HasMany
    {
        return $this->hasMany(ConditionOption::class);
    }

    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function getBusinessUnit(): BusinessUnit
    {
        return $this->businessUnit;
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'next_condition_id');
    }

    public function getNextCondition(): ?Condition
    {
        return $this->condition;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters & Setters
    |--------------------------------------------------------------------------
    */

    // ID
    public function getId(): int
    {
        return $this->attributes['id'];
    }

    // Label
    public function getLabel(): string
    {
        return $this->attributes['label'];
    }

    public function setLabel(string $value): void
    {
        $this->attributes['label'] = $value;
    }

    // Interaction Type

    public function getInteractionType(): string
    {
        return $this->attributes['interaction_type'];
    }

    public function setInteractionType(string $value): void
    {
        $this->attributes['interaction_type'] = $value;
    }

    // Type
    public function getType(): string
    {
        return $this->attributes['type'];
    }

    public function setType(string $value): void
    {
        $this->attributes['type'] = $value;
    }

    // Observation
    public function getObservation(): ?string
    {
        return $this->attributes['observation'] ?? null;
    }

    public function setObservation(?string $value): void
    {
        $this->attributes['observation'] = $value;
    }

    // Multiple

    public function getMultiple(): bool
    {
        return (bool) ($this->attributes['multiple'] ?? false);
    }

    public function setMultiple(bool $value): void
    {
        $this->attributes['multiple'] = $value;
    }

    // Section

    public function getSection(): ?string
    {
        return $this->attributes['section'] ?? null;
    }

    // Required

    public function isRequired(): bool
    {
        return (bool) ($this->attributes['required'] ?? true);
    }

    // Placeholder

    public function getPlaceholder(): ?string
    {
        return $this->attributes['placeholder'] ?? null;
    }

    // Multiline

    public function isMultiline(): bool
    {
        return (bool) ($this->attributes['multiline'] ?? false);
    }
}
