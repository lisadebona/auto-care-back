<?php

namespace App\Models;

use App\Enums\FeeType;
use App\Enums\ShopSuppliesCap;
use Database\Factories\FeeSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The shop's fee and tax rates. The table holds a single row.
 */
#[Fillable([
    'shop_supplies_cap',
    'shop_supplies_cap_amount',
    'shop_supplies_fee',
    'shop_supplies_fee_type',
    'shop_supplies_on_parts',
    'shop_supplies_on_labor',
    'epa_rate',
    'epa_on_parts',
    'epa_on_labor',
    'tax_rate',
    'tax_on_parts',
    'tax_on_labor',
    'tax_on_epa',
    'tax_on_shop_supplies',
    'tax_on_subcontract',
])]
class FeeSetting extends Model
{
    /** @use HasFactory<FeeSettingFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults so an unsaved instance reports them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'shop_supplies_cap' => 'none',
        'shop_supplies_cap_amount' => null,
        'shop_supplies_fee' => 0,
        'shop_supplies_fee_type' => 'percent',
        'shop_supplies_on_parts' => false,
        'shop_supplies_on_labor' => false,
        'epa_rate' => 0,
        'epa_on_parts' => false,
        'epa_on_labor' => false,
        'tax_rate' => 0,
        'tax_on_parts' => false,
        'tax_on_labor' => false,
        'tax_on_epa' => false,
        'tax_on_shop_supplies' => false,
        'tax_on_subcontract' => false,
    ];

    /**
     * Get the saved rates, or an unsaved instance with defaults when none exist yet.
     */
    public static function current(): self
    {
        return static::query()->firstOrNew();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shop_supplies_cap' => ShopSuppliesCap::class,
            'shop_supplies_cap_amount' => 'decimal:2',
            'shop_supplies_fee' => 'decimal:3',
            'shop_supplies_fee_type' => FeeType::class,
            'shop_supplies_on_parts' => 'boolean',
            'shop_supplies_on_labor' => 'boolean',
            'epa_rate' => 'decimal:3',
            'epa_on_parts' => 'boolean',
            'epa_on_labor' => 'boolean',
            'tax_rate' => 'decimal:3',
            'tax_on_parts' => 'boolean',
            'tax_on_labor' => 'boolean',
            'tax_on_epa' => 'boolean',
            'tax_on_shop_supplies' => 'boolean',
            'tax_on_subcontract' => 'boolean',
        ];
    }
}
