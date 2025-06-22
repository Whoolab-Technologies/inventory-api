<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaterialReturn extends BaseModel
{
    use HasFactory;
    protected $table = "material_returns";
    protected $fillable = ['from_store_id', 'to_store_id', 'status_id', 'dn_number'];


    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $attributes = [
        'status' => 'IN TRANSIT',
    ];
    protected static function booted()
    {
        static::creating(function ($model) {
            if (is_null($model->status_id)) {
                $model->status_id = 10;
            }
        });
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }
    public function details()
    {
        return $this->hasMany(MaterialReturnDetail::class, 'material_return_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(MaterialReturnItem::class, 'material_return_id', 'id');
    }
    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id', 'id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id', 'id');
    }

}
