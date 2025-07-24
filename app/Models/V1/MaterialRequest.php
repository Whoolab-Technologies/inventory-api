<?php
namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\StatusEnum;

class MaterialRequest extends BaseModel
{
    use HasFactory;

    protected $table = 'material_requests';

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'updated_at'];

    protected $fillable = [
        'request_number',
        'engineer_id',
        'store_id',
        'qr_code',
        'status_id',
    ];
    protected $appends = ['has_on_hold_shipment'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (is_null($model->status_id)) {
                $model->status_id = 2;
            }
        });
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'material_request_items')
            ->withPivot('quantity');
    }

    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function engineer()
    {
        return $this->belongsTo(Engineer::class);
    }



    public function scopeSearch($query, $search = null, $statusId = null, $dateFrom = null, $dateTo = null, $storeId = null, $engineerId = null)
    {

        if ($search) {
            $search = "%{$search}%";
            $query->where('request_number', 'LIKE', $search)
                ->orWhereHas('store', function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search);
                })
                ->orWhereHas('status', function ($q3) use ($search) {
                    $q3->where('name', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('engineer', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', $search)
                        ->orWhere('last_name', 'LIKE', $search);
                })
                ->orWhereHas('purchaseRequests', function ($q) use ($search) {
                    $q->where('purchase_request_number', 'LIKE', $search);
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
            $query->whereHas('store', function ($q) use ($storeId) {
                $q->where('id', $storeId);
            });
        }

        if ($engineerId) {
            $query->whereHas('engineer', function ($q) use ($engineerId) {
                $q->where('id', $engineerId);
            });
        }
        return $query;
    }
    public function materialRequestStockTransfer()
    {
        return $this->hasOne(MaterialRequestStockTransfer::class, 'material_request_id');
    }

    public function stockTransfers()
    {
        return $this->hasMany(
            StockTransfer::class,
            'request_id',
            'id'
        );
    }
    public function purchaseRequests()
    {
        return $this->hasMany(
            PurchaseRequest::class,
            'material_request_id',
            'id'
        );
    }

    public function getHasOnHoldShipmentAttribute()
    {
        return $this->purchaseRequests
            ->flatMap->lpos
            ->flatMap->shipments
            ->contains('status_id', StatusEnum::ON_HOLD->value);
    }

    public function files()
    {
        return $this->hasMany(MaterialRequestFile::class, 'material_request_id');
    }
}