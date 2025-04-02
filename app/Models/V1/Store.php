<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends BaseModel
{
    use HasFactory;

    protected $table = 'stores';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'location', 'type'];

    protected $append = ['is_central_store'];


    public function engineers()
    {
        return $this->hasMany(Engineer::class);
    }

    public function storekeepers()
    {
        return $this->hasMany(Storekeeper::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function engineerStocks()
    {
        return $this->hasMany(EngineerStock::class);
    }

    public function sentStockTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'from_store_id');
    }

    /**
     * Get stock transfers received by this store.
     */
    public function receivedStockTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'to_store_id');
    }

    public function stockTransferItems()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    public function getIsCentralStoreAttribute()
    {
        return $this->type == "central" ? true : false;
    }

}

