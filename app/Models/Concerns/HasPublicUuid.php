<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model): void {
            if (! filled($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $column = is_string($value) && Str::isUuid($value) ? 'uuid' : $this->getKeyName();

        return $this->resolveRouteBindingQuery($this, $value, $column)->first();
    }
}
