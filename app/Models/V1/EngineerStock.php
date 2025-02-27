<?php
namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngineerStock extends Model
{
    use HasFactory;

    protected $table = 'engineer_stock';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = [
        'store_id',
        'engineer_id',
        'product_id',
        'quantity',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function engineer()
    {
        return $this->belongsTo(Engineer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
