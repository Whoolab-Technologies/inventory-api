<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaterialReturn extends BaseModel
{
    use HasFactory;
    protected $table = "material_returns";
    protected $fillable = ['return_number', 'from_store_id', 'to_store_id', 'status_id', 'dn_number'];


    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    // protected $attributes = [
    //     'status' => 'IN TRANSIT',
    // ];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }
    public function details()
    {
        return $this->hasMany(MaterialReturnDetail::class, 'material_return_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(MaterialReturnItem::class, 'material_return_id', 'id');
    }
    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id', 'id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id', 'id');
    }

    public function scopeSearch($query, $term)
    {
        $term = "%{$term}%";

        return $query->where('dn_number', 'LIKE', $term)
            ->orWhereHas(
                'toStore',
                fn($q) =>
                $q->where('name', 'LIKE', $term)
            )
            ->orWhereHas(
                'fromStore',
                fn($q) =>
                $q->where('name', 'LIKE', $term)
            )
            ->orWhereHas(
                'details.engineer',
                fn($q) =>
                $q->where('first_name', 'LIKE', $term)
                    ->orWhere('last_name', 'LIKE', $term)
            )
            ->orWhereHas('items.product', function ($q) use ($term) {
                $q->where('item', 'LIKE', $term)
                    ->orWhere('cat_id', 'LIKE', $term)
                    ->orWhereHas(
                        'category',
                        fn($q2) =>
                        $q2->where('name', 'LIKE', $term)
                    )
                    ->orWhereHas(
                        'brand',
                        fn($q2) =>
                        $q2->where('name', 'LIKE', $term)
                    );
            });
    }

    public function files()
    {
        return $this->hasMany(MaterialReturn::class, 'material_return_id');
    }
}
