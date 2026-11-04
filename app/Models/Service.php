<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory, Searchable;

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'business_unit_id',
        'gestion_line_id',
    ];

    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * A Service belongs to a ServiceType.
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    /**
     * A Service belongs to a GestionLine.
     */
    public function gestionLine(): BelongsTo
    {
        return $this->belongsTo(GestionLine::class, 'gestion_line_id', 'id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the BusinessUnit.
     */
    public function getBusinessUnit(): BusinessUnit
    {
        return $this->businessUnit;
    }

    /**
     * Get the Gestion Line.
     */
    public function getGestionLine(): GestionLine
    {
        return $this->gestionLine;
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

    // Name
    public function getName(): string
    {
        return $this->attributes['name'];
    }

    public function setName(string $value): void
    {
        $this->attributes['name'] = $value;
    }
}
