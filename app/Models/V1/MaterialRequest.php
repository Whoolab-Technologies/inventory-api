<?php
namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Auth;

class MaterialRequest extends Model
{
    use HasFactory;

    protected $table = 'material_requests';

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'updated_at'];

    protected $fillable = ['request_number', 'engineer_id', 'store_id', 'status'];

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

    public function products()
    {
        return $this->belongsToMany(Product::class, 'material_request_items')
            ->withPivot('quantity');
    }

    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function engineer()
    {
        return $this->belongsTo(Engineer::class);
    }

    public function scopeSearch($query, $term)
    {
        $term = "%{$term}%";
        return $query->where('request_number', 'LIKE', $term)
            ->orWhereHas('store', function ($q) use ($term) {
                $q->where('name', 'LIKE', $term);
            })
            ->orWhereHas('engineer', function ($q) use ($term) {
                $q->where('first_name', 'LIKE', $term)
                    ->orWhere('last_name', 'LIKE', $term);
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
}