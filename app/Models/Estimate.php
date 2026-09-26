<?php

namespace App\Models;

use App\Enums\EstimateItemType;
use App\Enums\EstimateOrderStatus;
use App\Enums\EstimateWorkflow;
use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'number',
    'customer_id',
    'vehicle_id',
    'service_writer_id',
    'due_date',
    'customer_comments',
    'recommendations',
    'po_number',
    'completed_at',
    'payment_terms',
    'order_status',
    'workflow',
    'authorized_at',
    'labels',
])]
class Estimate extends Model
{
    public const DEFAULT_PAYMENT_TERMS = 'On Receipt';

    /**
     * @var list<string>
     */
    public const PAYMENT_TERMS = [
        'On Receipt',
        'Net 15',
        'Net 30',
        'Net 60',
    ];

    /** @use HasFactory<EstimateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['display_number', 'is_authorized', 'totals'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_terms' => self::DEFAULT_PAYMENT_TERMS,
        'order_status' => 'estimate',
        'workflow' => 'estimates',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function serviceWriter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'service_writer_id');
    }

    /**
     * @return HasMany<EstimateService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(EstimateService::class)->orderBy('sort_order');
    }

    public static function nextNumber(): int
    {
        $max = DB::table('estimates')->max('number');

        return $max === null ? 1000 : ((int) $max) + 1;
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayNumber(): Attribute
    {
        return Attribute::get(fn (): string => '#'.$this->number);
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isAuthorized(): Attribute
    {
        return Attribute::get(fn (): bool => $this->authorized_at !== null);
    }

    /**
     * @return Attribute<array{parts: string, labor: string, tires: string, subcontract: string, fees: string, grand_total: string}, never>
     */
    protected function totals(): Attribute
    {
        return Attribute::get(function (): array {
            $parts = 0.0;
            $labor = 0.0;
            $tires = 0.0;
            $subcontract = 0.0;
            $fees = 0.0;

            foreach ($this->relationLoaded('services') ? $this->services : [] as $service) {
                foreach ($service->relationLoaded('lineItems') ? $service->lineItems : [] as $item) {
                    $subtotal = (float) $item->subtotal;

                    match ($item->type) {
                        EstimateItemType::Part => $parts += $subtotal,
                        EstimateItemType::Labor => $labor += $subtotal,
                        EstimateItemType::Tire => $tires += $subtotal,
                        EstimateItemType::Subcontract => $subcontract += $subtotal,
                        EstimateItemType::Fee => $fees += $subtotal,
                    };
                }
            }

            $grand = $parts + $labor + $tires + $subcontract + $fees;

            return [
                'parts' => number_format($parts, 2, '.', ''),
                'labor' => number_format($labor, 2, '.', ''),
                'tires' => number_format($tires, 2, '.', ''),
                'subcontract' => number_format($subcontract, 2, '.', ''),
                'fees' => number_format($fees, 2, '.', ''),
                'grand_total' => number_format($grand, 2, '.', ''),
            ];
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'authorized_at' => 'datetime',
            'order_status' => EstimateOrderStatus::class,
            'workflow' => EstimateWorkflow::class,
            'labels' => 'array',
        ];
    }
}
