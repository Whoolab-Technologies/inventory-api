<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockTransaction extends BaseModel
{
    protected $table = 'stock_transactions';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = [
        'store_id',
        'product_id',
        'engineer_id',
        'stock_movement',
        'quantity',
        'type',
    ];
    use HasFactory;

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function engineer()
    {
        return $this->belongsTo(Engineer::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'store_id', 'store_id')->where('product_id', $this->product_id);
    }
}
