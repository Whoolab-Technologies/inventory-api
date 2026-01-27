<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequestStock extends BaseModel
{
    use HasFactory;
    protected $fillable = [
        'material_request_id',
        'purchase_request_id',
        'material_request_item_id',
        'product_id',
        'quantity',
    ];
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }
    public function item()
    {
        return $this->belongsTo(MaterialRequestItem::class, 'material_request_item_id');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }


    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
