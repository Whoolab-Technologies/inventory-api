<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMeta extends BaseModel
{
    protected $table = 'stock_metas';

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = [
        'store_id',
        'product_id',
        'quantity',
        'supplier',
        'lpo',
        'dn_number',
    ];
    use HasFactory;


}
