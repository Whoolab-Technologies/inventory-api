<?php
namespace App\Models\V1;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StockTransferNote extends Model
{
    use HasFactory;

    protected $fillable = ['stock_transfer_id', 'material_request_id', 'notes'];

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];


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


    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function createdBy()
    {
        return $this->created_type === 'engineer' ? $this->belongsTo(Engineer::class, 'created_by') : $this->belongsTo(Storekeeper::class, 'created_by');
    }

}