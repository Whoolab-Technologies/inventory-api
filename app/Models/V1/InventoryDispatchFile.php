<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
class InventoryDispatchFile extends BaseModel
{
    use HasFactory;

    protected $table = "inventory_dispatch_files";
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $appends = ['url'];

    protected $fillable = [
        'inventory_dispatch_id',
        'file',
        'file_mime_type'
    ];

    public function inventoryDispatch()
    {
        return $this->belongsTo(InventoryDispatch::class, 'inventory_dispatch_id');
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
