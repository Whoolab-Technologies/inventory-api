<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class Category extends BaseModel
{
    use HasFactory;
    protected $table = 'categories';

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

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }
}
