<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequestItem extends Model
{
    use HasFactory;

    protected $table = 'material_request_items';

    protected $fillable = ['material_request_id', 'product_id', 'quantity'];
    protected $hidden = ['created_at', 'updated_at'];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
