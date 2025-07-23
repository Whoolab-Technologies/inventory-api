<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class ProductMinStock extends BaseModel
{
    use HasFactory;
    protected $table = 'product_min_stocks';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = ['product_id', 'store_id', 'min_stock_qty'];


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

}
