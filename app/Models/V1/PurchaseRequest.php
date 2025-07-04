<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\StatusEnum;
class PurchaseRequest extends BaseModel
{
    protected $table = 'purchase_requests';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = ['purchase_request_number', 'material_request_id', 'material_request_number', 'lpo', 'do', 'status_id'];

    use HasFactory;
    protected $appends = ['created_datetime', 'has_on_hold_shipment'];

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

    public function prItems()
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }
    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }
    public function lpos()
    {
        return $this->hasMany(Lpo::class, 'pr_id');
    }

    public function transactions()
    {
        return $this->hasMany(StockTransfer::class, 'request_id', 'material_request_id')
            ->where('request_type', 'PR');
    }

    public function getHasOnHoldShipmentAttribute()
    {
        $hasOnholdShipments = $this->lpos
            ->flatMap->shipments
            ->contains('status_id', StatusEnum::ON_HOLD->value);
        return $hasOnholdShipments;
    }

}
