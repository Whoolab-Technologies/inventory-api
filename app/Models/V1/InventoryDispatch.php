<?php

namespace App\Models\V1;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryDispatch extends BaseModel
{
    use HasFactory;

    protected $table = 'inventory_dispatches';

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'updated_at'];

    protected $fillable = ['dispatch_number', 'delivery_note_number', 'engineer_id', 'store_id', 'self', 'representative', 'picked_at'];
    protected $casts = [
        'self' => 'boolean',
    ];

    public function engineer()
    {
        return $this->belongsTo(Engineer::class, 'engineer_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function items()
    {
        return $this->hasMany(InventoryDispatchItem::class, 'inventory_dispatch_id', 'id');
    }

    public function files()
    {
        return $this->hasMany(InventoryDispatchFile::class, 'inventory_dispatch_id');
    }

}
