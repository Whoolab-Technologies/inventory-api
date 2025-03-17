<?php
namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Auth;
class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = ['from_store_id', 'to_store_id', 'status', 'remarks',];

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (Auth::check()) {
                $user = Auth::user();
                $model->created_by = $user->id;
                $model->created_type = optional($user->currentAccessToken())->name; // Get token name if exists
                $model->updated_by = $user->id;
                $model->updated_type = optional($user->currentAccessToken())->name;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                $model->updated_by = $user->id;
                $model->updated_type = optional($user->currentAccessToken())->name;
            }
        });
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
        return $query->whereHas('stockTransferItems.product', function ($query) use ($term) {
            $query->where('item', 'LIKE', $term);
        })
            ->orWhereHas('fromStore', function ($q) use ($term) {
                $q->where('name', 'LIKE', $term);
            })
            ->orWhereHas('toStore', function ($q) use ($term) {
                $q->where('name', 'LIKE', $term);
            })
            ->orWhereHas('materialRequestStockTransfer.materialRequest', function ($q) use ($term) {
                $q->where('request_number', 'LIKE', $term)
                    ->orWhereHas('engineer', function ($q) use ($term) {
                        $q->where('first_name', 'LIKE', $term)
                            ->orWhere('last_name', 'LIKE', $term);
                    });
            });
    }
    public function notes()
    {
        return $this->hasMany(StockTransferNote::class, 'stock_transfer_id');
    }
}
