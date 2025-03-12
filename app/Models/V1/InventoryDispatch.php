<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Auth;
class InventoryDispatch extends Model
{
    use HasFactory;

    protected $table = 'inventory_dispatches';

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'updated_at'];

    protected $fillable = ['engineer_id', 'store_id', 'self', 'representative', 'picked_at'];

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

}
