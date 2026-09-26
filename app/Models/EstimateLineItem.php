<?php

namespace App\Models;

use App\Enums\EstimateItemType;
use Database\Factories\EstimateLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'estimate_service_id',
    'type',
    'description',
    'price',
    'quantity',
    'discount',
    'status',
    'sort_order',
])]
class EstimateLineItem extends Model
{
    /** @use HasFactory<EstimateLineItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['subtotal'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price' => 0,
        'quantity' => 1,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<EstimateService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(EstimateService::class, 'estimate_service_id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function subtotal(): Attribute
    {
        return Attribute::get(function (): string {
            $gross = (float) $this->price * (float) $this->quantity;
            $discount = (float) ($this->discount ?? 0);

            return number_format(max($gross - $discount, 0), 2, '.', '');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EstimateItemType::class,
            'price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'discount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
