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
     * Shop details printed on estimates and invoices.
     *
     * @return array{
     *     company_name: string|null,
     *     website: string|null,
     *     logo_url: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     timezone: string,
     *     address: string|null,
     *     city: string|null,
     *     state: string|null,
     *     zip_code: string|null,
     *     country: string
     * }
     */
    public function details(): array
    {
        return [
            'company_name' => $this->company_name,
            'website' => $this->website,
            'logo_url' => $this->logo_url,
            'email' => $this->email,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country ?? 'United States',
        ];
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
