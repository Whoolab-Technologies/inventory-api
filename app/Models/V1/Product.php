<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Product extends Model
{
    protected $table = 'products';
    protected $hidden = ['created_at', 'updated_at'];
    protected $appends = ['code', "image_url"];
    protected $fillable = [
        'item',
        'cost',
        'quantity',
        'unit_id',
        'item_description',
        'qr_code'
    ];
    use HasFactory;


    protected static function boot()
    {
        parent::boot();

        static::created(function ($product) {
            $stores = Store::all(); // Get all stores
            foreach ($stores as $store) {
                ProductStock::create([
                    'product_id' => $product->id,
                    'store_id' => $store->id,
                    'quantity' => $store->type == 'central' ? $product->quantity : 0
                ]);
            }
        });
    }

    public function stock()
    {
        return $this->hasMany(ProductStock::class, 'product_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // public function brand()
    // {
    //     return $this->belongsTo(Brand::class);
    // }

    public function getUnitCodeAttribute()
    {
        return $this->unit?->short_code;
    }
    public function getImageUrlAttribute()
    {
        return URL::to(Storage::url($this->image));
    }
    public function getCodeAttribute()
    {
        $url = "";
        if (isset($this->qr_code)) {
            $url = URL::to(Storage::url($this->qr_code));
            unset($this->qr_code);
        }
        return $url;
    }


}
