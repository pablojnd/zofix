<?php

namespace App\Models\SveParameter;

use App\Models\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'name', 'description', 'sve_unit_of_measurement_id', 'unit_name', 'unit_sigla', 'is_active',
])]
class SveUnitOfMeasurement extends Model
{
    use HasUlids, SoftDeletes;

    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sve_unit_of_measurement_id');
    }

    public function childUnits(): HasMany
    {
        return $this->hasMany(self::class, 'sve_unit_of_measurement_id');
    }

    public function tariffCodes(): HasMany
    {
        return $this->hasMany(SveTariffCode::class, 'sve_unit_of_measurement_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'sve_unit_of_measurement_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
