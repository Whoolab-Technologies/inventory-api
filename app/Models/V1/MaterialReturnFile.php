<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class MaterialReturnFile extends BaseModel
{
    use HasFactory;

    protected $table = "material_return_files";
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $appends = ['url'];

    protected $fillable = [
        'material_return_id',
        'file',
        'file_mime_type',
        'transaction_type'
    ];

    public function materialReturn()
    {
        return $this->belongsTo(MaterialReturn::class, 'material_return_id');
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
