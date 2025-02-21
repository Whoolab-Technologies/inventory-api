<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $table = 'stores';
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = ['name', 'location', 'storekeeper_id', 'type'];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($store) {
            $products = Product::all();
            foreach ($products as $product) {
                ProductStock::create([
                    'product_id' => $product->id,
                    'store_id' => $store->id,
                    'quantity' => 0
                ]);
            }
        });
    }

    public function storekeeper()
    {
        return $this->belongsTo(Storekeeper::class, 'storekeeper_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_stock', 'store_id', 'product_id')
            ->withPivot('quantity');
    }


    public function engineers()
    {
        return $this->belongsToMany(Engineer::class, 'engineer_store')->withTimestamps();
    }
}

