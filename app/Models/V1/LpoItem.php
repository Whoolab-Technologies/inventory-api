<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LpoItem extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'lpo_id',
        'pr_item_id',
        'product_id',
        'requested_quantity',
        'received_quantity',
    ];
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    public function lpo()
    {
        return $this->belongsTo(Lpo::class, 'lpo_id');
    }


    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function prItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'pr_item_id');
    }
}
