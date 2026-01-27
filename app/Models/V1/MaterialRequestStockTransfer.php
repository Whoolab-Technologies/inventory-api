<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequestStockTransfer extends Model
{
    use HasFactory;
    protected $table = 'material_request_stock_transfers';

    protected $fillable = ['material_request_id', 'stock_transfer_id',];

    protected $hidden = ['created_at', 'updated_at'];
    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

}