<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, HasUserTracking, SoftDeletes;

    protected $fillable = [
        'name',
        'created_by_user',
        'updated_by_user',
    ];

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
