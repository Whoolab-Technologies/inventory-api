<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StockMeta extends Model
{
    protected $table = 'stock_metas';

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = [
        'store_id',
        'product_id',
        'quantity',
        'supplier',
        'lpo',
        'dn_number',
    ];
    use HasFactory;
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (Auth::check()) {
                $user = Auth::user();
                $model->created_by = $user->id;
                $model->created_type = optional($user->currentAccessToken())->name; // Get token name if exists
                $model->updated_by = $user->id;
                $model->updated_type = optional($user->currentAccessToken())->name;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                $model->updated_by = $user->id;
                $model->updated_type = optional($user->currentAccessToken())->name;
            }
        });
    }

}
