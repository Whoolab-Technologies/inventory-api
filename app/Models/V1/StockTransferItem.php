<?php
namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class StockTransferItem extends BaseModel
{
    use HasFactory;

    protected $fillable = ['stock_transfer_id', 'product_id', 'quantity', 'requested_quantity', 'issued_quantity', 'received_quantity',];

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];


    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function stockInTransfer()
    {
        return $this->hasOne(StockInTransit::class, 'stock_transfer_item_id');
    }
}
