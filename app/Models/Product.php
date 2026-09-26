<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'description',
    'unit_price',
    'margin',
    'stock_quantity',
    'brand_id',
    'category_id',
    'upc_code',
    'part_number',
    'is_taxable',
    'image_path',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Product $product): void {
            $product->retail_price = static::calculateRetailPrice($product->unit_price, $product->margin);
        });
    }

    public static function calculateRetailPrice(float|string $unitPrice, float|string $margin): string
    {
        return number_format((float) $unitPrice * (1 + (float) $margin / 100), 2, '.', '');
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasOne<ProductImage, $this>
     */
    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_main', true);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mainImage?->url);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'margin' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_taxable' => 'boolean',
        ];
    }
}
