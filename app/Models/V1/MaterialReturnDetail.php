<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaterialReturnDetail extends BaseModel
{
    use HasFactory;

    protected $table = "material_return_details";
    protected $fillable = ['material_return_id', 'engineer_id'];
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    public function materialReturn()
    {
        return $this->belongsTo(MaterialReturn::class, 'material_return_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialReturnItem::class, 'material_return_detail_id');
    }
    public function engineer()
    {
        return $this->belongsTo(Engineer::class, 'engineer_id');
    }

}
