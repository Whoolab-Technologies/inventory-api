<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInTransit extends Model
{
    use HasFactory;
    protected $table = 'stock_in_transit';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'stock_transfer_id',
        'material_request_id',
        "material_request_item_id",
        'stock_transfer_item_id',
        'product_id',
        'material_return_id',
        "material_return_item_id",
        'issued_quantity',
        'received_quantity',
        'status_id'
    ];
    protected static function booted()
    {
        static::creating(function ($model) {
            if (is_null($model->status_id)) {
                $model->status_id = 10;
            }
        });
    }
    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function stockTransferItem()
    {
        return $this->belongsTo(StockTransferItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
