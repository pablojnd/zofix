<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'warehouse_id', 'name'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Inverse of Product::categories(); the pivot stores NOT NULL tenant columns.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category')
            ->withPivot(['company_id', 'warehouse_id'])
            ->withTimestamps();
    }
}
