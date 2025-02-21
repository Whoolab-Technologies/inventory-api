<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreEngineer extends Model
{
    use HasFactory;
    protected $table = 'engineer_store';
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = [
        'engineer_id',
        'store_id',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function engineer()
    {
        return $this->belongsTo(Engineer::class, 'engineer_id');
    }
}
