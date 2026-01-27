<?php

namespace App\Models\V1;

class Brand extends BaseModel
{
    protected $table = 'brands';

    protected $hidden = [
        'created_by',
        'created_type',
        'updated_by',
        'updated_type',
        'created_at',
        'updated_at'
    ];

    protected $fillable = [
        'name',
        'description'
    ];

}
