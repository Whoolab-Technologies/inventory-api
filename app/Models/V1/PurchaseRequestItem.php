<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PurchaseRequestItem extends BaseModel
{
    protected $table = 'purchase_request_items';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = ['purchase_request_id', 'material_request_item_id', 'product_id', 'quantity', 'received_quantity'];

    use HasFactory;

    public function pr()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function materialRequestItem()
    {
        return $this->belongsTo(MaterialRequestItem::class, 'material_request_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
