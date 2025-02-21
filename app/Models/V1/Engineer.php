<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Engineer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = "engineers";
    protected $fillable = ['first_name', 'last_name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token', 'created_at', 'updated_at'];

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'engineer_store')->withTimestamps();
    }
}
