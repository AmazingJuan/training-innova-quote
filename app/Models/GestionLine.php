<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GestionLine extends Model
{
    use Searchable;

    /**
     * Attributes:
     *
     * $this->attributes['id'] - int - Primary key identifier
     * $this->attributes['name'] - string - Name of the gestion line
     * $this->attributes['services'] - Service[] - Services associated with the gestion line
     */

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function getServices()
    {
        return $this->services()->get();
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
