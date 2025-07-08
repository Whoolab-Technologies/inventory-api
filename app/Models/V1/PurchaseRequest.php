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
    public function scopeSearch($query, $search = null, $statusId = null, $dateFrom = null, $dateTo = null, $storeId = null, $engineerId = null)
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_request_number', 'LIKE', "%{$search}%")
                    ->orWhere('material_request_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('materialRequest', function ($q2) use ($search) {
                        $q2->where('request_number', 'LIKE', "%{$search}%")
                            ->orWhereHas('store', function ($q3) use ($search) {
                                $q3->where('name', 'LIKE', "%{$search}%");
                            })
                            ->orWhereHas('engineer', function ($q3) use ($search) {
                                $q3->where('first_name', 'LIKE', "%{$search}%")
                                    ->orWhere('first_name', 'LIKE', "%{$search}%");
                            });
                    })
                    ->orWhereHas('status', function ($q3) use ($search) {
                        $q3->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('lpos', function ($q4) use ($search) {
                        $q4->where('lpo_number', 'LIKE', "%{$search}%")
                            ->orWhereHas('supplier', function ($q5) use ($search) {
                                $q5->where('name', 'LIKE', "%{$search}%");
                            });
                    });
            });
        }

        if ($statusId) {
            $query->where('status_id', $statusId);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($storeId) {
            $query->whereHas('materialRequest.store', function ($q) use ($storeId) {
                $q->where('id', $storeId);
            });
        }

        if ($engineerId) {
            $query->whereHas('materialRequest.engineer', function ($q) use ($engineerId) {
                $q->where('id', $engineerId);
            });
        }

        return $query;
    }


}
