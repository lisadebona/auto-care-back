<?php

namespace App\Models;

use Database\Factories\EstimateServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'estimate_id',
    'name',
    'notes',
    'authorized',
    'discount_percent',
    'epa_percent',
    'shop_supplies_percent',
    'tax_percent',
    'sort_order',
])]
class EstimateService extends Model
{
    /** @use HasFactory<EstimateServiceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['subtotal'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'authorized' => false,
        'discount_percent' => 0,
        'epa_percent' => 0,
        'shop_supplies_percent' => 0,
        'tax_percent' => 0,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    /**
     * @return HasMany<EstimateLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(EstimateLineItem::class)->orderBy('sort_order');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function subtotal(): Attribute
    {
        return Attribute::get(function (): string {
            $itemsTotal = 0.0;

            foreach ($this->relationLoaded('lineItems') ? $this->lineItems : [] as $item) {
                $itemsTotal += (float) $item->subtotal;
            }

            $afterDiscount = $itemsTotal * (1 - ((float) $this->discount_percent / 100));
            $epa = $afterDiscount * ((float) $this->epa_percent / 100);
            $shop = $afterDiscount * ((float) $this->shop_supplies_percent / 100);
            $taxable = $afterDiscount + $epa + $shop;
            $tax = $taxable * ((float) $this->tax_percent / 100);

            return number_format($taxable + $tax, 2, '.', '');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authorized' => 'boolean',
            'discount_percent' => 'decimal:3',
            'epa_percent' => 'decimal:3',
            'shop_supplies_percent' => 'decimal:3',
            'tax_percent' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }
}
