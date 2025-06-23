<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
class PurchaseRequest extends BaseModel
{
    protected $table = 'purchase_requests';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = ['purchase_request_number', 'material_request_id', 'material_request_number', 'lpo', 'do', 'status_id'];

    use HasFactory;
    protected $appends = ['created_datetime'];

    public function getCreatedDateTimeAttribute()
    {
        return $this->created_at ? $this->created_at : null;
    }
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }
    // Example: PR belongs to MaterialRequest
    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }

    public function transactions()
    {
        return $this->hasMany(StockTransfer::class, 'request_id', 'material_request_id')
            ->where('type', 'PR');
    }

}
