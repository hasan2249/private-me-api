<?php

namespace App\Models\Auth;

use App\Models\BaseModel;

/**
 * Class User.
 */
class Plan extends BaseModel
{
    protected $fillable = [
        'name',
        'duration',
        'description',
        'price_year',
        'price_month',
        'storage',
        'free_storage',
        'friends',
        'chat'
    ];
}
