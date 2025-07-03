<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LpoShipmentItem extends BaseModel
{
    use HasFactory;
    protected $table = "lpo_shipment_items";

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    protected $fillable = [
        'lpo_shipment_id',
        'lpo_item_id',
        'product_id',
        'quantity_delivered',
    ];

    public function lpoShipment()
    {
        return $this->belongsTo(LpoShipment::class, 'lpo_shipment_id');
    }

    public function lpoItem()
    {
        return $this->belongsTo(LpoItem::class, 'lpo_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
