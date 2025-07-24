<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class MaterialRequestFile extends BaseModel
{
    use HasFactory;

    protected $table = "material_request_files";
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $appends = ['url'];

    protected $fillable = [
        'material_request_id',
        'file',
        'file_mime_type',
        'transaction_type'
    ];
    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
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
