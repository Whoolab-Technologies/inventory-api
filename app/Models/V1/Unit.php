<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'units';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'short_code'
    ];
}
