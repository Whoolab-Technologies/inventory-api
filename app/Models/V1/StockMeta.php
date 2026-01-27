<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMeta extends BaseModel
{
    use HasFactory;

    protected $table = 'stock_metas';

    protected $hidden = [
        'created_by',
        'created_type',
        'updated_by',
        'updated_type',
        'created_at',
        'updated_at'
    ];

    protected $fillable = [
        'store_id',
        'product_id',
        'quantity',
        'supplier_id',
        'lpo',
        'dn_number',
    ];

    protected $appends = ['supplier_name'];

    /**
     * Supplier Relationship
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Global Scope to Eager Load Supplier
     */
    protected static function booted()
    {
        static::addGlobalScope('supplier', function ($query) {
            $query->with('supplier');
        });
    }

    /**
     * Supplier Name Accessor
     */
    public function getSupplierNameAttribute()
    {
        return $this->supplier ? $this->supplier->name : null;
    }
}
