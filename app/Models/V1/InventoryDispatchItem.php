<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Auth;

class InventoryDispatchItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_dispatch_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['inventory_dispatch_id', 'product_id', 'quantity'];

    public function inventoryDispatch()
    {
        return $this->belongsTo(InventoryDispatch::class, 'inventory_dispatch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
