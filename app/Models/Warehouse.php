<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'name', 'slug', 'code', 'email_contact', 'phone_contact', 'address'])]
class Warehouse extends Model
{
    use HasUlids;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
