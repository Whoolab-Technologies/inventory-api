<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;
    protected $table = "statuses";
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = [
        'name',
        'code',
        'color',
        'description',
        // add 'type' if applicable
    ];

    /**
     * A status can be assigned to many material requests.
     */
    public function materialRequests()
    {
        return $this->hasMany(MaterialRequest::class);
    }
    public function materialReturns()
    {
        return $this->hasMany(MaterialReturn::class);
    }
    public function stockTransfer()
    {
        return $this->hasMany(StockTransfer::class);
    }
    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }
}