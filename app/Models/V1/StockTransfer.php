<?php
namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class StockTransfer extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'from_store_id',
        'to_store_id',
        'status_id',
        'dn_number',
        'remarks',
        'request_id',
        'request_type',
        'transaction_type',
        'send_by',
        'sender_role',
        'received_by',
        'receiver_role',
        'note',
    ];

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $appends = ['date'];
    protected static function booted()
    {
        static::creating(function ($model) {
            if (is_null($model->status_id)) {
                $model->status_id = 10;
            }
        });
    }
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }
    /**
     * Get the store from which stock is transferred.
     */
    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    /**
     * Get the store to which stock is transferred.
     */
    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }
    // public function materialRequestStockTransfers()
    // {
    //     return $this->hasMany(MaterialRequestStockTransfer::class, 'stock_transfer_id');
    // }
    public function materialRequestStockTransfer()
    {
        return $this->hasOne(MaterialRequestStockTransfer::class, 'stock_transfer_id');
    }
    public function stockTransferItems()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }


    public function scopeSearch($query, $term)
    {
        $term = "%{$term}%";

        return $query->where(function ($query) use ($term) {
            $query->whereHas('stockTransferItems.product', function ($query) use ($term) {
                $query->where('item', 'LIKE', $term);
            })
                ->orWhereHas('fromStore', function ($q) use ($term) {
                    $q->where('name', 'LIKE', $term);
                })
                ->orWhereHas('toStore', function ($q) use ($term) {
                    $q->where('name', 'LIKE', $term);
                })
                ->orWhereHas('materialRequest', function ($q) use ($term) {
                    $q->where('request_number', 'LIKE', $term)
                        ->orWhereHas('engineer', function ($q) use ($term) {
                            $q->where('first_name', 'LIKE', $term)
                                ->orWhere('last_name', 'LIKE', $term);
                        });
                });
        });
    }

    public function notes()
    {
        return $this->hasMany(StockTransferNote::class, 'stock_transfer_id');
    }


    public function files()
    {
        return $this->hasMany(StockTransferFile::class, 'stock_transfer_id');
    }

    // public function materialRequests()
    // {
    //     return $this->belongsToMany(MaterialRequest::class, 'material_request_stock_transfer');
    // }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'request_id', 'id');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id', 'material_request_id');
    }
    public function pickup()
    {
        return $this->belongsTo(InventoryDispatch::class, 'request_id', 'id');
    }
    public function getDateAttribute()
    {
        return $this->created_at ? $this->created_at->format('Y-m-d h:i a') : null;
    }
}
