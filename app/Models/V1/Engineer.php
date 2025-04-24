<?php

namespace App\Models\V1;

use App\Models\V1\BaseModel;
use App\Models\V1\Store;
use App\Models\V1\EngineerStock;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Engineer extends BaseModel implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, Notifiable;

    protected $table = 'engineers';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'store_id',
        'department_id'
    ];
    protected $appends = ['name'];
    protected $hidden = [
        'password',
        'remember_token',
        'created_by',
        'created_type',
        'updated_by',
        'updated_type',
        'created_at',
        'updated_at'
    ];

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function stocks()
    {
        return $this->hasMany(EngineerStock::class);
    }

    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

}
