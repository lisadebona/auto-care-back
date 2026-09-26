<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'customer_id',
    'year',
    'make',
    'model',
    'sub_model',
    'transmission',
    'engine_size',
    'drivetrain',
    'type',
    'mileage',
    'vin',
    'image_path',
])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const TRANSMISSIONS = [
        'Automatic',
        'Manual',
        'CVT',
        'Dual-Clutch',
        'Other',
    ];

    /**
     * @var list<string>
     */
    public const DRIVETRAINS = [
        'FWD',
        'RWD',
        'AWD',
        '4WD',
        'Other',
    ];

    /**
     * @var list<string>
     */
    public const TYPES = [
        'Sedan',
        'SUV',
        'Truck',
        'Van',
        'Coupe',
        'Hatchback',
        'Wagon',
        'Convertible',
        'Motorcycle',
        'Other',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['name', 'image_url'];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = array_filter([
                (string) $this->year,
                $this->make,
                $this->model,
                $this->sub_model,
            ]);

            return implode(' ', $parts);
        });
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null);
    }
}
