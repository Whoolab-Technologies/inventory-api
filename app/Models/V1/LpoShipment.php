<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LpoShipment extends Model
{
    use HasFactory;
    protected $table = "lpo_shipments";

    protected $fillable = [
        'lpo_id',
        'dn_number',
        'date',
        'status_id',
        'remarks',
    ];

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    public function lpo()
    {
        return $this->belongsTo(Lpo::class, 'lpo_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
    public function items()
    {
        return $this->hasMany(LpoShipmentItem::class, 'lpo_shipment_id');
    }
    public function files()
    {
        return $this->hasMany(LpoFile::class, 'lpo_shipment_id');
    }
}
