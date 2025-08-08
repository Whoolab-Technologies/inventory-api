<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class LpoFile extends BaseModel
{
    use HasFactory;

    protected $table = "lpo_files";
    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];
    protected $appends = ['url'];

    protected $fillable = [
        'lpo_id',
        'lpo_shipment_id',
        'file',
        'file_mime_type',
    ];


    public function lpo()
    {
        return $this->belongsTo(Lpo::class);
    }

    public function shipment()
    {
        return $this->belongsTo(LpoShipment::class);
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
