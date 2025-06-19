<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaterialReturnItem extends BaseModel
{
    use HasFactory;

    protected $table = "material_return_items";
    protected $fillable = ['material_return_id', 'material_return_detail_id', 'product_id', 'issued', 'returned'];
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    public function materialReturnDetail()
    {
        return $this->belongsTo(MaterialReturnDetail::class, 'material_return_detail_id');
    }

    public function materialReturn()
    {
        return $this->belongsTo(MaterialReturn::class, 'material_return_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
