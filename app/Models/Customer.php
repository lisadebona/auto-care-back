<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'first_name',
    'last_name',
    'home_address',
    'home_city',
    'home_state',
    'home_zip_code',
    'home_country',
    'office_address',
    'office_city',
    'office_state',
    'office_zip_code',
    'office_country',
    'phone',
    'phone_2',
    'email',
    'notes',
])]
class Customer extends Model
{
    public const DEFAULT_COUNTRY = 'United States';

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['name'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'home_country' => self::DEFAULT_COUNTRY,
        'office_country' => self::DEFAULT_COUNTRY,
    ];

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }
}
