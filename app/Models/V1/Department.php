<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\V1\BaseModel;

class Department extends BaseModel
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'name',
        'description'

    ];
    protected $hidden = [
        'created_by',
        'created_type',
        'updated_by',
        'updated_type',
        'created_at',
        'updated_at'
    ];

}
