<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class Lpo extends BaseModel
{
    use HasFactory;
    protected $table = "lpos";
    protected $fillable = [
        'lpo_number',
        'pr_id',
        'supplier_id',
        'status_id',
        'date',
    ];

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'pr_id');
    }

    public function purchaseRequestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'pr_item_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(LpoItem::class, 'lpo_id');
    }
    public function shipments()
    {
        return $this->hasMany(LpoShipment::class, 'lpo_id');
    }
    public function files()
    {
        return $this->hasMany(LpoFile::class, 'lpo_id');
    }
}
