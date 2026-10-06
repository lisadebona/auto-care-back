<?php

namespace App\Models;

use Database\Factories\CustomWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['name', 'value'])]
class CustomWorkflow extends Model
{
    /** @use HasFactory<CustomWorkflowFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetLabels());
        static::deleted(fn () => self::forgetLabels());
    }

    public static function valueForNew(): string
    {
        return 'custom-'.Str::uuid();
    }

    /**
     * @return array<string, string>
     */
    public static function labelsByValue(): array
    {
        /** @var array<string, string> $labels */
        $labels = Cache::store('array')->rememberForever(
            'custom-workflow-labels',
            fn (): array => self::query()->pluck('name', 'value')->all(),
        );

        return $labels;
    }

    public static function forgetLabels(): void
    {
        Cache::store('array')->forget('custom-workflow-labels');
    }
}
