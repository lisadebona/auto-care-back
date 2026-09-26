<?php

namespace App\Models;

use Database\Factories\GeneralSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The shop's company information. The table holds a single row.
 */
#[Fillable([
    'company_name',
    'website',
    'email',
    'phone',
    'timezone',
    'address',
    'city',
    'state',
    'zip_code',
    'country',
])]
class GeneralSetting extends Model
{
    /** @use HasFactory<GeneralSettingFactory> */
    use HasFactory;

    /**
     * Get the saved settings, or an unsaved instance with defaults when none exist yet.
     */
    public static function current(): self
    {
        return static::query()->firstOrNew([], [
            'timezone' => config('app.timezone'),
        ]);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null);
    }
}
