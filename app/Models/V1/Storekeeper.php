<?php

namespace App\Models\V1;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Storekeeper extends BaseModel implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'store_id'];
    protected $hidden = ['password', 'remember_token', 'created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function token()
    {
        return $this->hasOne(UserToken::class, 'user_id', 'id')
            ->where('user_role', 'storekeeper')->latest('created_at');
    }
}
