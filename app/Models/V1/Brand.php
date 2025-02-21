<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'brands';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'description'
    ];
}
