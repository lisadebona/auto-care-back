<?php

namespace App\Models;

use App\Enums\EstimateItemType;
use Database\Factories\CannedJobLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'canned_job_id',
    'type',
    'description',
    'price',
    'quantity',
    'discount',
    'remarks',
    'sort_order',
])]
class CannedJobLineItem extends Model
{
    /** @use HasFactory<CannedJobLineItemFactory> */
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
     * @return BelongsTo<CannedJob, $this>
     */
    public function cannedJob(): BelongsTo
    {
        return $this->belongsTo(CannedJob::class);
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
            'remarks' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
