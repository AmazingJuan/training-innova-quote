<?php

namespace App\Models;

use App\Enums\ConditionRole;
use App\Enums\FieldType;
use App\Enums\InteractionType;
use App\Enums\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question of a business unit's request form (the "encuadre"). Questions are
 * chained through next_condition_id; options may open branches.
 */
class Condition extends Model
{
    /**
     * Attributes:
     *
     * $this->attributes['id'] - int - Primary key identifier
     * $this->attributes['label'] - string - The question, as the client reads it
     * $this->attributes['interaction_type'] - InteractionType - How the client answers it
     * $this->attributes['type'] - FieldType - Kind of value asked for (default: text)
     * $this->attributes['observation'] - string|null - Help text under the question
     * $this->attributes['placeholder'] - string|null - Example of the expected answer, shown inside the field
     * $this->attributes['multiline'] - bool - Free text answered in a long field
     * $this->attributes['multiple'] - bool - Several options can be chosen
     * $this->attributes['section'] - Section|null - Block of the encuadre it belongs to
     * $this->attributes['role'] - ConditionRole|null - What the application does with its answer
     * $this->attributes['required'] - bool - Whether the client must answer it
     * $this->attributes['next_condition_id'] - int|null - ID of the next question in the chain
     * $this->attributes['business_unit_id'] - int - ID of the business unit it belongs to
     */

    /**
     * Mass assignable attributes.
     *
     * @var list<string>
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
        'role',
        'required',
        'placeholder',
        'multiline',
    ];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'interaction_type' => InteractionType::class,
            'type' => FieldType::class,
            'section' => Section::class,
            'role' => ConditionRole::class,
            'multiple' => 'boolean',
            'required' => 'boolean',
            'multiline' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return HasMany<ConditionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ConditionOption::class);
    }

    /** @return Collection<int, ConditionOption> */
    public function getOptions(): Collection
    {
        return $this->options;
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

    /** @return BelongsTo<Condition, $this> */
    public function nextCondition(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'next_condition_id');
    }

    public function getNextCondition(): ?Condition
    {
        return $this->nextCondition;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Condition>  $query
     * @return Builder<Condition>
     */
    public function scopeWithRole(Builder $query, ConditionRole $role): Builder
    {
        return $query->where('role', $role->value);
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

    public function getLabel(): string
    {
        return $this->attributes['label'];
    }

    public function setLabel(string $value): void
    {
        $this->attributes['label'] = $value;
    }

    public function getInteractionType(): InteractionType
    {
        return $this->interaction_type;
    }

    public function setInteractionType(InteractionType $value): void
    {
        $this->interaction_type = $value;
    }

    public function getType(): FieldType
    {
        return $this->type ?? FieldType::Text;
    }

    public function setType(FieldType $value): void
    {
        $this->type = $value;
    }

    public function getObservation(): ?string
    {
        return $this->attributes['observation'] ?? null;
    }

    public function setObservation(?string $value): void
    {
        $this->attributes['observation'] = $value;
    }

    public function getMultiple(): bool
    {
        return (bool) ($this->attributes['multiple'] ?? false);
    }

    public function setMultiple(bool $value): void
    {
        $this->attributes['multiple'] = $value;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function getRole(): ?ConditionRole
    {
        return $this->role;
    }

    public function hasRole(ConditionRole $role): bool
    {
        return $this->getRole() === $role;
    }

    public function isRequired(): bool
    {
        return (bool) ($this->attributes['required'] ?? true);
    }

    public function getPlaceholder(): ?string
    {
        return $this->attributes['placeholder'] ?? null;
    }

    public function isMultiline(): bool
    {
        return (bool) ($this->attributes['multiline'] ?? false);
    }

    public function isChoice(): bool
    {
        return $this->getInteractionType() === InteractionType::Options;
    }
}
