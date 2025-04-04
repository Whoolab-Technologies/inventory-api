<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class StockTransferFile extends BaseModel
{
    protected $table = 'stock_transfer_files';
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $fillable = [
        'stock_transfer_id',
        'file',
        'file_mime_type',
        'transaction_type',
    ];
    protected $appends = ['url'];
    use HasFactory;

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function getUrlAttribute()
    {
        $url = "";
        if (!empty($this->file)) {
            $url = URL::to(Storage::url($this->file));
        }
        return $url;
    }

}
