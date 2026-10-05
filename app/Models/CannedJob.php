<?php

namespace App\Models;

use Database\Factories\CannedJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class CannedJob extends Model
{
    /** @use HasFactory<CannedJobFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['subtotal'];

    /**
     * @return HasMany<CannedJobLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(CannedJobLineItem::class)->orderBy('sort_order');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function subtotal(): Attribute
    {
        return Attribute::get(function (): string {
            $total = 0.0;

            foreach ($this->relationLoaded('lineItems') ? $this->lineItems : [] as $item) {
                $total += (float) $item->subtotal;
            }

            return number_format($total, 2, '.', '');
        });
    }
}
