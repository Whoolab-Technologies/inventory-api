<?php

namespace App\Models\V1;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Product extends BaseModel
{
    protected $table = 'products';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $appends = ['code', 'symbol', 'image_url', 'category_name', 'brand_name', 'product_category'];
    protected $fillable = [
        'item',
        'cat_id',
        'unit_id',
        'description',
        'qr_code',
        'category_id',
        'brand_id',
        'image',
        'remarks',
    ];
    use HasFactory;


    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // public function brand()
    // {
    //     return $this->belongsTo(Brand::class);
    // }

    public function getCategoryNameAttribute()
    {
        $categoryName = $this->category?->name ?? "";
        unset($this->category);
        return $categoryName;
    }
    public function getProductCategoryAttribute()
    {
        $categoryId = $this->category?->category_id ?? "";
        unset($this->category);
        return $categoryId;
    }

    public function getBrandNameAttribute()
    {
        $brandName = $this->brand?->name ?? "";
        unset($this->brand);
        return $brandName;

    }

    public function getSymbolAttribute()
    {
        $symbol = $this->unit?->symbol;
        unset($this->unit);
        return $symbol;
    }
    public function getImageUrlAttribute()
    {
        if (!empty($this->image)) {
            return URL::to(Storage::url($this->image));
        }
        return "";
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

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }


    public function engineerStocks()
    {
        return $this->hasMany(EngineerStock::class, 'product_id', 'id');
    }

    public function stocksInTransit()
    {
        return $this->hasMany(StockInTransit::class, 'product_id', 'id')
            ->where("status", "in_transit");
    }

    public function scopeSearch($query, $term)
    {
        $term = "%{$term}%";
        return $query->where('item', 'LIKE', $term)
            ->orWhere('description', 'LIKE', $term)
            ->orWhere('cat_id', 'LIKE', $term)
            ->orWhereHas('unit', function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                    ->orWhere('symbol', 'LIKE', $term);
            });

        //     $term = "%{$term}%";
        // return $query->where(function ($q) use ($term) {
        //     $q->where('item', 'LIKE', $term)
        //         ->orWhere('description', 'LIKE', $term)
        //         ->orWhereHas('unit', function ($q) use ($term) {
        //             $q->where('name', 'LIKE', $term)
        //                 ->orWhere('symbol', 'LIKE', $term);
        //         });
        // });
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function minStocks()
    {
        return $this->hasMany(ProductMinStock::class);
    }
    public function locations()
    {
        return $this->hasMany(Location::class);
    }
    public function minStockForStore($storeId)
    {
        return $this->minStocks()->where('store_id', $storeId)->first();
    }
    public function locationForStore($storeId)
    {
        return $this->locations()->where('store_id', $storeId)->first();
    }
}
