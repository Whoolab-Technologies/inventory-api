<?php
namespace App\Models\V1;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockTransferNote extends BaseModel
{
    use HasFactory;

    protected $fillable = ['stock_transfer_id', 'material_request_id', 'notes'];

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];



    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function createdBy()
    {
        return $this->created_type === 'engineer' ? $this->belongsTo(Engineer::class, 'created_by') : $this->belongsTo(Storekeeper::class, 'created_by');
    }

}